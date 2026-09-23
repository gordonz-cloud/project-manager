# service 层怎么写

**适用范围**:用 Eloquent,且业务已经复杂到值得有 service 层。小项目 controller + Eloquent 就够,硬加一层是过度设计。

下面全是**默认值,不是法律**。碰到不适用的场合,破例,但要在注释或 commit 里写明为什么——照着不适用的规则写出更差的代码,比破例严重得多。

目标:service 读起来像伪代码,一行一句话——**同一个方法体里的语句保持同一个抽象高度**(Kent Beck 的 Composed Method)。念一遍就知道:如果一句是「记账」、下一句是「遍历数组第二层」,高度就混了,读的人得不停切焦距。

## 十条

1. 动词跟着数据走:写库动词长在 Model,推导动词长在承载那份数据的对象,service 只编排不干活。
2. 一次编排里有两个以上写库动词,事务边界归 service,不归 Model。外部调用(发 HTTP、推平台)不许放在事务里。
3. 需要被追问的数据结构不许裸奔:给它一个类,让每个问题变成一个方法名。包到什么程度看下面三问,别一见数组就包。
4. 一个意思只许有一种拼法。缺键和 null 都表示「没数据」,就在构造时塌成一种,别让每个调用方各判一次。
5. 命令做完事就返回一个能自己回答问题的结果对象,不要在调用方攒计数变量。
6. 业务层要基于一个 readonly 对象造它的修改版时,变形动词长在那个对象上(`at()`、`comparedAgainst()`),别在业务层写一遍全字段具名参数。纯搬运的 DTO 不需要。
7. 名字用生意词不用技术词:`confirmedQuantities()`,不是 `filterResults()`。
8. 注释只写为什么;发现自己在注释「这段在干嘛」,缺的是名字,不是注释。决策过程写进 commit,不写进注释。
9. 一个概念只用一个词。同一件事在变量名、返回键、方法名、输出文本里必须同名——换个说法就是让读的人维护一张翻译表。
10. 条件用正面词(`hasNoMother()`,不是 `! hasMother()`);守卫子句把不合格的挡在门口,主线永远留在最左边。**嵌套超过两层,先问能不能抽成有名字的方法**——每进一层,读的人就得在心里多压一个栈,这是最贵的一种开销。真的必须深(例如一次遍历同时算两件事,拆开就多一轮查询)就在注释里写明为什么。

## 一坨数据要不要包成类:三问

1. **它走多远?** 跨层 → 包。只在一个方法里,或跨方法但有 array shape 标注、静态分析查得住 → 不包。
2. **有人向它提问吗?** 同一个追问出现在第二处,或一个意思有两种拼法 → 包,提问变方法名。只被遍历、只被求和 → 不包。
3. **它的字段一起变吗?** 三个以上总是同进同出 → 包。

**Q2 为是必包。只有 Q1 或只有 Q3 为是,带类型标注的数组就够。** 三问全否还包 = 过度设计;Q2 为是却不包 = 逻辑漏回业务层。

例外记一笔:一个类的职责就是拿住另一个有行为的对象时(如 `Allocation` 持有 `AllocatedTargets`),它自己没有方法也该留——退回数组会变成「数组里塞对象」,比两种纯粹形态都难读。

## Collection 还是 array

**元素必须有名字,容器是次要问题。** `list<PlannedPush>` 和 `Collection<int, PlannedPush>` 读起来一样轻;`list<array{六个键}>` 两种容器都是地狱。

**If an array's fields are a model's fields, the input/output should be that model or a collection of models**. Bare arrays must not impersonate models. Exempt: plain identifier lists (ids/skus) and meaningful-keyed maps.

- 装 Eloquent 模型 → **Collection**(本来拿到的就是它,转 array 是白拷一份还丢掉 API)。
- 键有意义的 map → **array**。Collection 的 `filter()` 保留键、`values()` 才重置,而 `Eloquent\Collection::only()/except()` 按**模型主键**过滤而不是数组键——会静默出错。
- 跨契约边界的值对象列表 → **`list<X>` array**,类型最紧。中间变形用 Collection、定型的边界用 array:Collection 让变形便宜、让类型收紧变贵(`values()->all()` 丢 `list<>`、`filter()` 不收窄可空、`modelKeys()` 不是 `list<int>`,都得手写标注)。
- 流式、大小未知 → **LazyCollection**,且用 `tapEach()` 不用 `each()`——后者是 eager 的,会让上游生成器再跑一遍。

## 查询积木

Model 上把每个**业务谓词**做成一块积木(scope),service 组装它们并决定怎么终结(`get()` / `first()` / `count()` / `chunk()`)。这是 Fowler 的 Query Object 加 Evans 的 Specification,Laravel 的落地形态就是 scope 和自定义 Eloquent Builder。

- **判据:这个 `where` 有没有一个生意名字?** 有 → 做成积木(`pushable()`、`nonFba()`、`snapshotPredates()`)。没有(纯 id 过滤、纯技术条件)→ 留在原地,包了只是多一次跳转。
- **scope 里的 `orWhere` 必须自己包一层 `where(function ...)`**,否则它会漏出去,把调用方的其它条件一起 OR 掉——而且静默。
- **scope 只加条件**:不 `get()`、不 `orderBy`、不 `limit`、不 `with`。把排序或 eager load 藏在听起来像过滤器的名字里,比裸写 `where` 更难查。
- 静态取数方法(`Order::newestUpdatedAt()`)是终结形态,合法,但名字要让人知道它会执行、返回的是数据不是 builder。

## 目录怎么分

- **根目录只留入口**,让人一眼知道从哪读起。
- **跨层共享的数据类型单独成一个目录**(`Data/`),依赖方向才写得进 `deptrac.yaml` 强制。
- **只在本域内部用的值对象,跟生产它的那个类住一起。**
- 一个目录超过十来个文件就该分组,分组按业务阶段命名。收益是「十几个一模一样的图标」变成几组、根目录一眼看见入口,不是让字母序变成调用序——那做不到。

出处:Fowler 与 Beck 的 Data Clump 和 Feature Envy、Page-Jones 的 Connascence 局部性(距离越远,耦合必须越弱)、Ousterhout 对 classitis 的批评(类切太碎,复杂度就跑到看不见的类间关系上)、Martin 的 Screaming Architecture(顶层目录该喊出业务,不是喊出框架)。

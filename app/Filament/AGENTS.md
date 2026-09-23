# Filament 层领域知识

以下是框架行为的实测结论(Filament 5,2026-08-04 红队验证),不是猜测。升级 Filament 后逐条重验。

## multiple Select 的整单作废陷阱(最容易踩)

`in:` 校验规则来自 `getInValidationRuleValues()`:**任一已选值**不在 options 里、或命中 disableOptionWhen,它就返回空数组 → 整个字段所有值一起校验失败 → action 根本不执行,只报一句「The selected stores is invalid」。实测:选 1 个过期项 + 1 个正常项,两个都废。

结论:凡是按实时状态过滤的多选(店铺、任务…),**不许 disable、不许从 options 里删掉任何可能已被选中的项**。正确做法:留在 options 里用 label 标注(「— already published」「— switched off」),提交端过滤并在通知里点名跳过原因(skippedPublished 模式)。

## live 字段先于校验重建 schema

`->live()` 的字段每次变化就是一个请求,schema 立即按新值重算——而 options/`in:` 校验要到**提交**才跑。所以任何被 live 表单状态驱动的查询(比如按 store_ids 拉平台类目)必须自带与提交端**一字不差**的授权谓词链(`active()->visibleTo($user)`)。三处(options、visible/schema、action)各查一次库,漏一处就是越权窗口——曾经就是 schema 这处漏了 visibleTo,运营可借别人店铺的凭证发真实 API。

## hidden/disabled 是 server 端复核的,但没有测试钉着

当前版本 `mountAction`/`callMountedAction` 都会查 `isDisabled()`(且 hidden 蕴含 disabled),所以 UI 上藏掉的 action 伪造请求也调不动;disabled 的表单字段不 dehydrate、关系 Select disabled 时不保存,伪造提交塞不进去。这层可信,**但整个测试套件没有一条锁死它**——升级 Filament 把那句 `if ($this->isHidden()) return true;` 拿掉就静默失守。

## disabled 字段不 dehydrate,只读的展示列要显式 `->dehydrated()`(2026-08-26)

Repeater 里放一个 `->disabled()` 的 `mother_sku` 当行标识,提交上来的每一行**只有可编辑的那一格**——标识整列消失,成本全都不知道属于谁。`->disabled()->dehydrated()` 才拿得回来。

这跟上面那条「disabled 的表单字段不 dehydrate」是同一件事的两面:防伪造是它,丢数据也是它。

## 会写库的裁决类 action(查结果 → 改状态)

双保险,一个都不能少:action 的 `visible()` 限定状态(只有 Published 能查) + 模型写入用**条件 UPDATE**(`where status = Published`,未命中不写并告知)。只有前者防不了并发:重发在途时旧裁决把 Publishing 打回 Failed → Retry 复现 → 双 claim → 平台上重复商品。

## 母件列表的顶部是 sticky,不是内层滚动框(2026-08-14)

搜索、筛选钉在视口顶部(`.fi-resource-mother-skus .fi-ta-header-ctn`),卡片在下面滚。内层滚动框会吃掉滚轮事件、压缩可视卡片数、移动端出现两条滚动条。CSS 挂在全局 STYLES_AFTER 那个 hook 文件里,用页面 class 限定作用范围。

**粘的必须是 `.fi-ta-header-ctn`,不是里面的 `.fi-ta-header-toolbar`**(2026-08-20 生产实测)。sticky 只在自己父元素的盒子里活动;Filament 后来给工具栏套了一层 `fi-ta-header-ctn`,而这层和工具栏一样高(61px / 61px),等于没有活动空间——选择器照样匹配,粘性无声地失效,业主某天发现「以前能用现在不行了」。再往上一层是 `.fi-ta-main`(3133px),才有地方粘。

**这类「CSS 还在、选择器还匹配、就是不生效」先量父子高度**,再怀疑 `overflow`。这次 `.fi-layout` 上确实挂着 `overflow-x: clip`,看着像元凶,实测去掉毫无变化——不是它。

**钉住头部的代价:里面的下拉面板也被钉住了**(2026-08-25)。`.fi-dropdown-panel` 自己没有高度上限(`max-height: none`、`overflow: visible`,生产实测),头部会滚的时候面板跟着滚上来还够得着,钉住之后超出视口的部分永远够不着——筛选面板八个字段,直接看不到底。所以钉住头部的页面必须同时给里面的下拉面板 `max-height` + `overflow-y: auto`。高度按**钉住后**的位置算(头部停在 4rem),那是它空间最小的时候。

## Filament 5 的勾选状态在浏览器端

`selectedRecords` 是 Alpine 的 Set,服务端只有在下一次 Livewire 请求时才通过 `selectedTableRecords` 拿到。想在服务端渲染「勾了什么」就得每次勾选发一个请求。

**作用域链只对 Alpine 表达式生效,不对 x-data 里自己定义的方法生效**:`x-data="{ sync() { this.getSelectedRecordsCount() } }"` 里的 `this` 是子作用域,那个方法不存在,x-effect 会静默失败。要用父组件的方法,把调用写在 `x-effect="..."` 表达式里。

(2026-08-14 曾据此做过一个「挑选购物车」,勾选后在顶部列出选中的母件;实测始终没能在生产上正常显示,业主决定放弃,代码已移除。要重做先翻这段历史。)

## 覆盖了 Filament 的分页视图(2026-08-14)

`resources/views/vendor/filament/components/pagination/index.blade.php` 是 filament/support v5.7.1 那份的**逐字拷贝**,只在末尾加了「跳转到第几页」的输入框——7901 个母件是 330 页,而页码链接永远只给首二、末二和邻近几页,别的页以前要手改 URL。

**升级 Filament 时**:拿新版 vendor 文件和这份 diff 一遍,重新拷贝再把标记块贴回去。加在视图里而不是包一层组件,是因为输入框必须坐在分页条里页码旁边,Filament 没有给这个位置留插槽。

页数超过 2 页才显示。输入值在前端夹到 [1, 最后一页],不做校验也不报错——330 页的表里输 900 就是「到最后」,空的或 0 就是「到开头」。

## 记录路由绑定走 getEloquentQuery()

改 URL 的 record id 拿不到店铺范围外的行(ScopesQueryToAssignedStores 生效)——这层已验证可信,新 Resource 记得挂同款 scope。

## 图片尺寸:只认组件自己的 API,别写 class 也别写 style

`ImageColumn` / `ImageEntry` 渲染时**在你给的 style 后面追加自己的 `width`/`height`**
(`ImageEntry.php:531`、`ImageColumn.php:508`),后写的赢——所以 `extraImgAttributes` 里传
尺寸永远不生效。而且**面板的 CSS 是预编译的**,PHP 里现写的 Tailwind 类名(`w-full`
`max-w-24` `object-cover`)不在产物里,同样是死的。

- 尺寸走 `->imageWidth()` / `->imageHeight()`(或 `->square()`)。
- 只有 `object-fit`、`border-radius` 这类**它不追加的属性**才适合放 `extraImgAttributes` 的 style。
- 两者都不设 width 时,默认只给 height(entry `8rem` / column `2.5rem`),图按原图比例
  拉伸,宽度不受控——这就是图片压到旁边文字上的原因。
- **`max-width` 是唯一能压过它的写法**(不同属性,上限优先)。详情页图片可能比列宽大几像素,
  加 `max-width:100%` 兜底;但**列表里别加**——列窄时宽被压、高没被压,图会变成竖长条。

症状是「我设了尺寸但看起来没变化」时,先去读组件源码,别换写法试——2026-08-14 为此来回改了六轮。


## 一行放几个按钮

裁决类表格容易长成一行八个按钮(判图/保留/确认不符/填件数/确认/加车/拒绝/撤回),
读起来是一堵墙,真正该点的那个反而不显眼。

**一个主按钮 + `ActionGroup` 收起其余**。主按钮按行的状态互斥:待裁决→「值得买」、
已确认→「加入采购车」、已拒绝→「撤回裁决」,任何时刻只显示一个。

顺带:**「撤销」这种词单独看不知道撤销什么**——2026-08-17 被问过一次。
裁决类动作的标签要带宾语(「撤回裁决」),确认框再说清后果(「回到待裁决,
谁判过的记录保留」)。

## Filter 的 query() 跑在嵌套 where 里,聚合条件会被静默丢弃(2026-08-25)

`applyFiltersToTableQuery()` 把每个 filter 的 `apply()` 包进
`$query->where(function (Builder $query) { ... })`。加到那个**内层** builder 上的
`havingRaw`/`groupBy`/`join` 不会出现在最终 SQL 里——**不报错、不警告**,筛选器点了
跟没点一样,而 `assertSee` 型断言还会假通过(行本来就在)。

判据:条件读的是 **join 进来的聚合列**(窗口花费、ACOS、预算消耗率)→ 必须用
`->modifyBaseQueryUsing()`,它走 `applyToBaseQuery()`,直接挂在主查询上,且自己判
`isActive`。条件是普通列的 `where` → `->query()` 照常用。

顺带两条同源的坑:

- 测布尔 `Filter` 要写 `->filterTable('name', true)`。只给名字等于没勾,于是
  `assertSee` 假通过、`assertDontSee` 才暴露问题——**聚合筛选器的测试必须有一条
  `assertDontSee`**,否则测不到东西。
- 分组查询(`groupBy` + 聚合列)要 `->defaultKeySort(false)`。Filament 默认追加主键
  兜底排序,`ORDER BY id` 撞 `only_full_group_by` 直接 SQL 报错。

## ImageColumn 的两个坑:占位符不读 tooltip、非 URL 的 state 会被当磁盘路径(2026-09-16)

**`->tooltip()` 在没有图的行上根本不渲染。** 有图走一个分支、没图走 `toEmbeddedHtml()` 的
blank 分支,后者只读 `->emptyTooltip()`。两个都要挂,否则「为什么这行没有图」的提示
只在有图的行上有——而那正好是不需要它的行。这个错测试抓不到(断言 enum/方法都对),
悬停一下就看见了。

**`->state()` 给的字符串只有 `filter_var($s, FILTER_VALIDATE_URL)` 通过时才原样当地址用**,
否则 Filament 拿它去 `config('filament.default_filesystem_disk')` 拼路径。所以 `N/A`
渲染成对私有桶的 404,带空格的地址也一样。而单元格的 `href` 只做 HTML 转义、**不查 scheme**,
`javascript:...` 会变成后台里点一下就执行的链接。

判据:**state 来自我们不写的表(仓库库、爬虫库、平台回包)时,先过一道 scheme 白名单**
(`https://` 开头 + `FILTER_VALIDATE_URL`),不合格的当「没有图」。`FbmSkuImage::normalizeUrl`
那种只挡 `false`/`null`/`none` 的清洗不够。

**图源会死,而 `checkFileExistence(false)` 之后没人先去查**(查就是每行一个请求)。
用 `extraImgAttributes` 里的 `onerror` 把格子放回 `<p class="fi-ta-placeholder">—</p>`,
不要只 `display:none`——空格子读起来像页面坏了,破折号读起来是「这行没有图」。

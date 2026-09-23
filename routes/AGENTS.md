# 路由层领域知识

## api.php 是给机器用的门，锁只有令牌

路径保密不算安全。每条路由三层中间件缺一不可:`auth:sanctum`(没令牌不回答)+ `abilities:<能力>`(而且要是**为这道门发的**令牌,只挂 auth 等于一把钥匙开全楼)+ `throttle`(调用方的死循环不能拖垮生产库)。只写读的动词,要加写入先问业主。

返回值一律 API Resource **逐字段列出**,永远不 `return $model`——否则以后加一列就自动泄露给所有调用方,没人会发现。

## 令牌账号不给角色

`User::canAccessPanel()` 要求「未停用**且**有角色」,所以不给角色 = 这套凭据只能走 API。`api:token` 会拒绝给有角色的后台账号发令牌。停用账号一并断掉令牌靠 `RefuseDisabledApiUser`(挂在 api 组);它不能复用 `EnsureUserIsActive`——那个做登出加跳转,令牌请求两样都没有。

令牌命令是 `api:token` / `api:token:list` / `api:token:revoke`。吊销是删一行,不用重新部署。

> 坑:Sanctum 不注册 `abilities` 别名,`bootstrap/app.php` 里补了。少了它路由会抛「Target class [abilities] does not exist」——是报错不是拒绝,容易误判成程序 bug。

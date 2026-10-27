# 怎么指挥 Codex（边用边改）

## 起任务
- 先 `scripts/worktree-setup.sh <名字>`，一单一个 worktree。
- 在那个 worktree 里起（任务按当前目录归属，`status`/`result` 也要在同一目录查）：
  `node ~/.claude/plugins/cache/openai-codex/codex/1.0.6/scripts/codex-companion.mjs task --background --write "<任务书>"`
- 查：同目录 `... status <job> --json`（字段是 `"status": "running"`，带空格）、`... result <job>`。
- 审：`... review --base master` 或 `... adversarial-review --base master "<关注点>"`，在 worktree 里跑。

## 环境坑
- `--write` 的沙箱默认不让连网，连本地 docker Postgres 也不行，测试全跑不了。已在 `~/.codex/config.toml` 加 `[sandbox_workspace_write] network_access = true`。
- worktree 的 git 目录在主仓库 `.git` 里，沙箱默认不让写，codex 提交会失败（index.lock）。试过加 `writable_roots` 也没用（codex 一律保护 .git，已删掉这行）。所以 codex 不提交，由我在 worktree 里 commit。任务书里写「不要 commit，改完留在工作区」。
- 根目录 AGENTS.md 被 gitignore，worktree 里本来没有；setup 脚本会拷进去。

## 任务书写法
- 开头说清：在哪个分支、先读 AGENTS.md、目标、验收标准（能量的写数字）。
- 写死规则：只跑改到的测试文件、不跑全量；pint、phpstan（按 phpstan.neon 范围）0 错；不 commit、不推送，改完留在工作区（我来提交）。
- 要它最后交报告：改了什么、测试和变异验证结果。
- PHPStan 只查 app/ 等，不查 tests/。不要写「对改动文件跑 phpstan」——它会对测试文件跑，再去改已有测试凑 0 错。写「`vendor/bin/phpstan analyse --memory-limit=2G`（按 phpstan.neon 的范围）」。
- 写明「已有测试一行不动，diff 里只能有新增的 it()」。它顺手重构的倾向很强。
- 测试节点的意思要给全（带祖先链和预期），它会按字面挑一个最容易的场景。

## 观察记录
- 2026-10-27 第一单（树变快）：沙箱连不上库时，1 分 27 秒就停下来要人帮忙，没有硬绕。好习惯，但会白等，所以起任务前先确认环境。
- 2026-10-27 树变快（R23）：约 6 分钟。先量再改，三次取中位数，找到真根因（每条规则 5 个相关子查询），没上缓存，改动只有一个 scope；自带变异验证。测试偏重（解析 EXPLAIN），可接受。codex:review 无意见。
  - 注意它量的是本地库副本：本地 370ms 看不出线上几秒的慢。线上要另外用 `EXPLAIN ANALYZE` 量服务端时间（新查询 24ms）。下次任务书里直接要它报 SQL 执行时间，不报整页墙钟。
- 2026-10-27 补测试缺口：9 分钟判完 32 个节点（15 过时、16 补测、1 代码问题），证据都给到迁移文件行号，变异验证 6 个都红，判断靠得住。毛病：因为任务书让它对测试跑 phpstan，它改了 5 个文件里 80 多行已有测试；T159 挑了个跟节点原意不同的场景。用 `--resume-last` 续同一线程改，比重开省上下文。
- 续改那轮：只用约 5 分钟，撤回干净（0 行删除），T159 换成本意后测试通过、没发现代码问题。

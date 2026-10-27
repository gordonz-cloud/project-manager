# 怎么指挥 Codex（边用边改）

## 起任务
- 先 `scripts/worktree-setup.sh <名字>`，一单一个 worktree。
- 在那个 worktree 里起（任务按当前目录归属，`status`/`result` 也要在同一目录查）：
  `node ~/.claude/plugins/cache/openai-codex/codex/1.0.6/scripts/codex-companion.mjs task --background --write "<任务书>"`
- 查：同目录 `... status <job> --json`（字段是 `"status": "running"`，带空格）、`... result <job>`。
- 审：`... review --base master` 或 `... adversarial-review --base master "<关注点>"`，在 worktree 里跑。

## 环境坑
- `--write` 的沙箱默认不让连网，连本地 docker Postgres 也不行，测试全跑不了。已在 `~/.codex/config.toml` 加 `[sandbox_workspace_write] network_access = true`。
- 根目录 AGENTS.md 被 gitignore，worktree 里本来没有；setup 脚本会拷进去。

## 任务书写法
- 开头说清：在哪个分支、先读 AGENTS.md、目标、验收标准（能量的写数字）。
- 写死规则：只跑改到的测试文件、不跑全量；pint、phpstan 0 错；commit 前缀带规则号，`git add` 只加自己的文件，不推送。
- 要它最后交报告：改了什么、测试和变异验证结果。

## 观察记录
- 2026-10-27 第一单（树变快）：沙箱连不上库时，1 分 27 秒就停下来要人帮忙，没有硬绕。好习惯，但会白等，所以起任务前先确认环境。

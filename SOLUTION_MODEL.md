# Project Manager 事实模型

主线是 `需求树 → 规则`，规则关联 commit。

## 需求树

`requirements.parent_id` 构成目标 → 子目标 → 分组 → 规则的树。
`status` 只记录拍板：提议、已定、冲突、以后做、作废。
说法或状态改变自动写入 `requirement_revisions`，并清除 `accepted_at`，重新等待验收。
写库入口是 `requirements:save <json>`；节点的 `tests` 键已删除，传入时明确报错，包括空值和空列表。

## 提交与验收

`commit_requirement` 关联规则与提交。`commits:sync` 从提交消息中的 `R<number>` 自动建立关联，保留已有关系。

交付状态由 `DeliveryStatus::of(Requirement)` 推导，不存库：

- `accepted_at` 有值：已验证。
- 否则有至少一个关联 commit：待验收。
- 否则返回 null；已定规则仍计入待做。

`needs_review` 保留为既有字段，不参与交付状态计算。
树汇总只计已定叶节点的交付状态，另计提议和冲突；待决策优先，其次待做、待验收、完成。
未交付的已定节点不会因其他节点完成而变成完成。

## 独立数据模型

`data_models` 和 `model_fields` 独立保留，不关联规则。

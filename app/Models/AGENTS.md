# model 层怎么写

- Model 只负责访问数据库：查询、关系、scope、casts、访问器、写库动词和单模型不变量。
- 业务工作流、跨模型编排、外部调用不进入 Model；这些属于 service。
- **If an array's fields are a model's fields, the input/output should be that model or a collection of models**。Bare arrays must not impersonate models. Exempt: plain identifier lists (ids) and meaningful-keyed maps。
- 写库动词和业务谓词(scope)长在 Model 上，service 只编排——完整规矩见 `app/Services/CLAUDE.md`。
- 关系的父子/同项目校验放在 Model 生命周期钩子里；禁止把可推导的重复外键当第二事实源。

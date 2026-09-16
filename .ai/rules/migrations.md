---
paths:
  - 'database/migrations/**'
---

# Migrations

## Pivot tables carry no project_id

Pivot tables (module_requirement, data_model_feature, feature_test, project_user) do not carry a project_id column. Both sides of a pivot being in the same project is not enforced by a database constraint — it is an invariant maintained by the application layer, since every operation goes through a tenant-scoped model (BelongsToProject) that already restricts records to the current project.

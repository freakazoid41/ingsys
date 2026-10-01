# Roles & Notification Groups Admin Mechanics

## 1. Overview

Two admin screens backed by DB tables (migrated from JSON in Apr 2026): role templates + permission catalog, and notification type assignments per user. Roles propagate to users; notification groups route `notif-*` mails.

**Key files:**
- `app/Http/Controllers/PersonsController.php` — `rolesTemplate:158`, `rolesItems:310`, `notificationGroups:324`, `saveNotificationGroups:331`, `getNotificationUsers:374`
- `app/Services/RoleTemplateService.php` — `getRoleTemplates:29`, `saveRoleTemplates:46`, `deleteRoleTemplate:99`, `getPermissionCatalogs:140`, `getNotificationTypes:206`
- `app/Providers/PersonsServiceProvider.php` — `roleTemplateTrans:437`, `updateUserPermissions:453`, `updateUserNotificationGroups:469`, `getNotificationUsers:645`
- `app/Models/SysRoleTemplate.php`, `SysPermissionCatalog.php`, `SysNotificationType.php`, `SysRoleTemplateAudit.php`
- `routes/api.php:47-54` (all inside `auth:sanctum + CheckPermissionVersion`)
- `resources/js/pages/coalsystem/Roles/Roles.vue`, `Notifications/NSettings.vue`
- `resources/js/lib/treeModal.js`, `resources/js/stores/permissiondata.js`

## 2. Roles API

| Method | URL | Handler |
|--------|-----|---------|
| `GET` | `/v1/roles/templates` | `rolesTemplate` → `roleTemplateTrans(get)` → `RoleTemplateService::getRoleTemplates` (cache `sys_role_templates_all`, 3600s) → `{success,data:[{id,name,op_key,description,permissions[],immutable}]}` |
| `POST` | `/v1/roles/templates` | bulk create+update, see §3 |
| `DELETE` | `/v1/roles/templates/{id}` | `roleTemplateTrans(delete)` → `deleteRoleTemplate` (find by id, audit `deleted`, invalidate, return remaining array) |
| `GET` | `/v1/roles/items` (defined twice `:50`+`:54`) | `rolesItems` → `getPermissionCatalogs` (cache `sys_permission_catalogs_all`) → `buildPermissionTree` + `getPermissionChildren` recursive → `[{parent_id,title,ttitle:Perm_con_ops,ctitle:type_id,group_key:op-perm,op_key:code,childs}]`, parents = `metadata.parent_code==empty` |

Frontend `permissiondata.js:18,31` fetches both; `Roles.vue:92-97` normalises `permissions[], op_keys, op_key=op_key||slug(name)`.

### POST payload

`Roles.vue:123-147 persistGroups`: `{roles: JSON.stringify([{id,name,op_key,description,permissions:[op_key...],op_keys,created_at}])}`. `addGroup:180-193` uses `id=editingRoleId||Date.now()`, `op_key=existing||slug(name)`.

Backend `:175-280`: accepts array or JSON-string, `422` if not array or `!name||!permissions[]`. `normalizeOpKey:190-192` lower + spaces→`-`. Diff vs existing by `id→op_key→slug(name)`, canonical sort, compare `permissions|name|description|op_key` → `changes=[{added|updated,before,after}]`. Only if changed: `updateUserPermissions(op_key??id, permissions)`. Save via `updateOrCreate(id|op_key|name)`, preserve `op_key`, default `role-Ymdhi`, audit `updated`, `invalidateCaches()`, echo roles. Logs `UserLog log-role-update`.

`updateUserPermissions:453-467`: `User::where(role=id)` → per user `upsertConnectionEntity(person_id, type=op-doc-user-permission-form, tag={pid}**userpermissiongroup**{pid}, json(permissions))` + `refreshUserPermissionCache`.

## 3. Immutable roles — frontend only

`Roles.vue:42`: `['Tedarikçi','Satınalma Personeli','Satınalma KeyUser','Admin','Super Admin']`. Missing names injected on load as `{id:immutable-slug, permissions:[]}` without persisting (avoids audit spam). Delete blocked with Swal, `Sil` hidden, `Sabit` badge shown. No backend enforcement — DELETE never checks name/`immutable` column (only passthrough).

## 4. TreeModal cascade (`treeModal.js`)

`Roles.vue:210-236 renderPermissionTree`: `TreeModal.render({target:#roles-tree-container, items, idKey:op_key, parentKey:parent (non-existent, skips flat-merge), labelKey:title, childrenKey:childs, defaultChecked, onChange})`.

- Normalise `assignIds:29-62`, `buildTreeFromFlat:11-27` only if `parentKey` exists.
- Initial check `createNodeElement:137-183`: `checkedSet.has(id||op_key)` from `normalizeCheckedInput:95-126` (string→JSON/split, `{Value}` unwrap).
- Change `attachHandlers:215-232`: `setChildrenChecked` all descendants, `updateAncestorState:190-209` walks up (`checked=all, indeterminate=some&&!all`), emits `getCheckedValues:234-256` (only checked, indeterminate alone excluded). `Roles.vue:224-234` maps to `Set(op_key||id)`.

## 5. Notification groups API

| Method | URL | Handler | Gate |
|--------|-----|---------|------|
| `GET` | `/v1/notification/groups` | `notificationGroups` → `getNotificationTypes` → `buildNotificationTree` → `[{parent_id,title,group_key:op-notif,op_key:code}]` | none (any authed) |
| `GET` | `/v1/notification-users` | `getNotificationUsers` raw SQL `persons⨝con_ops⨝options(op-doc-user-notification-form)⨝entities⨝users`, `json_decode(entity_value)` → `{[op_key]:[{person_id:qnid,name,username}]}` | none, try/catch 500 |
| `POST` | `/v1/set-notification-groups` | `saveNotificationGroups`, see below | `per-00-01` else 401 |

Frontend `NSettings.vue:170-199` loads both; maps groups to `{id,title,op_key}`, users to `{id:person_id,name,username}`.

`formCallback:54-89`: `assignedMap={person_id:Set(op_key)}` from `groupMembers`, `allTouched=Set(touchedUserIds ∪ keys)`, `assigned=[{person_id:String,op_keys:[]}]` (empty clears), `POST {assigned:JSON.stringify}`.

Backend `:331-372`: `assigned` string→`json_decode`, calls `updateUserNotificationGroups` + `UserLog log-notification-group-update` **before** `is_array` check (side-effect runs even on 422). Success `{success,data}`.

`updateUserNotificationGroups:469-482`: per `{person_id:qnid,op_keys}` → `Persons where qnid` → `upsertConnectionEntity(type=op-doc-user-notification-form, tag={id}**usernotificationgroup**{id}, json(op_keys)||'[]')`.

`touchedUserIds:39,156-167,218-222`: seeded from initial members, pushed on add **and** remove, flushed with `assignedMap.keys` so deletions of never-touched users persist as `op_keys:[]`.

Frontend gate `NSettings.vue:379`: `<AppFab v-if="permissions.includes('per-00-01')">` — hides save, read-only otherwise.

## 6. Gates

- `per-04-03` roles admin (`rolesTemplate:162-165`, `rolesItems:313-316` → 401). No `v-if` in `Roles.vue`.
- `per-00-01` notif save (`saveNotificationGroups:334-339`). Reads open.
- `per-04-02` persons CRUD (contrast).

## 7. Gotchas

1. Backend never enforces `immutable` — add check or rename attack deletes system role.
2. `roles/items` route duplicated — harmless but clean up.
3. `saveNotificationGroups` logs before validating — reorder.
4. `updateUserPermissions` matches `User.role==op_key??id` — keep `op_key` stable or users orphan.
5. Tree `indeterminate` alone not submitted — partial parent without leaf = lost.
6. Read endpoints ungated — intentional but document as public-to-authed.

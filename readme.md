# Etcd v3 PHP Client

<p align="center">
  <a href="#"><img src="https://img.shields.io/badge/php-8.1+-blue.svg" alt="PHP Version"></a>
  <a href="#"><img src="https://img.shields.io/badge/etcd-v3.5+-green.svg" alt="Etcd Version"></a>
  <a href="#"><img src="https://img.shields.io/badge/license-MIT-yellow.svg" alt="License"></a>
</p>

> 一个基于 `chenbool/curl` 的 Etcd v3 API PHP 客户端，支持 KV 操作、租约管理、认证授权、集群管理等功能。

---

## 📋 目录

- [特性](#-特性)
- [环境要求](#-环境要求)
- [安装](#-安装)
- [快速开始](#-快速开始)
- [详细使用指南](#-详细使用指南)
  - [KV 键值对操作](#kv-键值对操作)
  - [租约管理](#租约管理)
  - [认证与授权](#认证与授权)
  - [集群管理](#集群管理)
  - [维护操作](#维护操作)
  - [监控与调试](#监控与调试)
- [API 参考](#api-参考)
- [完整示例](#-完整示例)
- [项目结构](#-项目结构)

---

## ✨ 特性

| 特性 | 描述 |
|------|------|
| 🔑 **KV 操作** | 支持 Put、Get、Delete、Watch 等完整 KV 操作 |
| ⏰ **租约管理** | 支持 Lease 的创建、撤销、续约、TTL 查询 |
| 🔐 **认证授权** | 支持用户、角色、权限管理，JWT Token 认证 |
| 🏗️ **集群管理** | 支持成员管理、Leader 转移、快照备份 |
| 🔧 **维护功能** | 支持压缩、碎片整理、告警管理 |
| 📊 **监控接口** | 支持健康检查、Metrics、Pprof 性能分析 |

---

## 🔧 环境要求

| 项目 | 版本 |
|------|------|
| PHP | >= 8.1 |
| Etcd | >= 3.5 |
| cURL 扩展 | 已安装 |

---

## 📦 安装

```bash
composer require chenbool/curl
```

---

## 🚀 快速开始

```php
<?php
require __DIR__ . '/vendor/autoload.php';

use chenbool\Etcd\Client;

// 创建客户端实例
$client = new Client('127.0.0.1:2379');

// 设置键值
$client->put('name', 'etcd-client');
echo "Put name: etcd-client\n";

// 获取值
$value = $client->get('name');
echo "Get name: {$value}\n";

// 删除键
$client->del('name');
echo "Delete name\n";
```

---

## 📖 详细使用指南

### KV 键值对操作

#### 设置值（Put）

```php
// 基本设置
$client->put('redis', '127.0.0.1:6379');
echo "Put redis: 127.0.0.1:6379\n";

// 设置值并返回旧值（prev_kv）
$result = $client->put('redis', '127.0.0.1:6579', ['prev_kv' => true]);
echo "Put redis with prev_kv\n";
// 如果有旧值，可以在 $result 中获取

// 设置值并绑定租约（键会在租约过期后自动删除）
$lease = $client->grant(3600);  // 创建 3600 秒的租约
$leaseId = $lease['ID'] ?? 0;
if ($leaseId > 0) {
    $client->put('redis', '127.0.0.1:6579', ['lease' => $leaseId]);
    echo "Put redis with lease: {$leaseId}\n";
}
```

#### 获取值（Get）

```php
// 获取单个键的值
$value = $client->get('redis');
echo "Get redis: {$value}\n";

// 获取所有键
$keys = $client->getAllKeys();
echo "All keys: " . implode(', ', $keys) . "\n";

// 获取原始响应（包含元数据如 version、create_revision、mod_revision 等）
$raw = $client->getRaw('redis');
echo "Raw data: " . json_encode($raw) . "\n";

// 获取时指定版本号
$value = $client->get('redis', ['revision' => 100]);

// 仅获取计数
$count = $client->get('prefix/', ['count_only' => true, 'range_end' => "prefix/\xFF"]);
```

#### 删除键（Delete）

```php
// 删除单个键
$client->del('redis');
echo "Delete redis\n";

// 删除并返回旧值
$result = $client->del('redis', ['prev_kv' => true]);

// 范围删除（删除所有以 "config/" 开头的键）
$client->del('config/', ['range_end' => "config/\xFF"]);
```

#### 监听键变化（Watch）

```php
// 持续监听键的变化（阻塞式）
$client->watch('redis', function($result) {
    if (isset($result['events'])) {
        foreach ($result['events'] as $event) {
            echo "事件类型: " . $event['type'] . "\n";  // PUT 或 DELETE
            echo "键: " . base64_decode($event['kv']['key']) . "\n";
            if (isset($event['kv']['value'])) {
                echo "值: " . base64_decode($event['kv']['value']) . "\n";
            }
        }
    }
});

// 单次监听
$watchResult = $client->watchOnce('redis');
if ($watchResult) {
    echo "Watch ID: " . $watchResult['watch_id'] . "\n";
}

// 从指定版本开始监听
$client->watch('redis', $callback, ['revision' => 100]);

// 监听时包含旧值
$client->watch('redis', $callback, ['prev_kv' => true]);
```

#### 数据压缩（Compaction）

```php
// 压缩到指定版本（删除该版本之前的历史数据）
try {
    $client->compaction(100);
    echo "Compaction 100\n";
} catch (\Exception $e) {
    echo "Compaction failed: " . $e->getMessage() . "\n";
}

// 物理压缩（彻底删除数据，释放空间）
$client->compaction(100, true);
```

---

### 租约管理

租约用于给键设置生存时间（TTL），适用于临时数据、服务注册等场景。

```php
// 授予租约，TTL 为 3600 秒（1小时）
$lease = $client->grant(3600);
echo "Grant lease: " . json_encode($lease) . "\n";
// 输出示例: {"ID": 7587894738292371267, "TTL": 3600}

// 指定 ID 授予租约（注意：如果 ID 已存在会报错）
$leaseId = 7587894738292371267;
try {
    $client->revoke($leaseId);  // 先撤销已存在的
} catch (\Exception $e) {
    // 忽略不存在的错误
}
$lease = $client->grant(3600, $leaseId);
echo "Grant lease with ID: " . json_encode($lease) . "\n";

// 使用租约设置键值（键将在租约过期后自动删除）
$client->put('service/node1', '192.168.1.100:8080', ['lease' => $leaseId]);

// 撤销租约（立即删除关联的所有键）
$client->revoke($leaseId);
echo "Revoke lease\n";

// 保持租约活跃（续约）
$client->keepAlive($leaseId);
echo "Keep alive lease\n";

// 查询租约信息
$ttl = $client->timeToLive($leaseId);
echo "Time to live: " . json_encode($ttl) . "\n";
// 输出: {"ID": 7587894738292371267, "TTL": 3600}

// 查询租约信息并获取关联的键
$ttlWithKeys = $client->timeToLive($leaseId, true);
echo "TTL with keys: " . json_encode($ttlWithKeys) . "\n";
// 输出: {"ID": ..., "TTL": ..., "keys": ["service/node1"]}

// 列出所有租约
$leases = $client->leases();
echo "All leases: " . json_encode($leases) . "\n";
// 输出: [7587894738292371267, 7587894738292371268]
```

---

### 认证与授权

Etcd 支持基于用户、角色的细粒度访问控制。

#### 启用认证流程

```php
// 注意：启用认证前必须先创建 root 用户！

// 步骤 1: 创建 root 用户
$client->addUser('root', 'root123');
echo "Add root user\n";

// 步骤 2: 创建 root 角色（通常已存在）
$client->addRole('root');
echo "Add root role\n";

// 步骤 3: 授予 root 用户 root 角色
$client->grantUserRole('root', 'root');
echo "Grant root role to root user\n";

// 步骤 4: 启用认证
$client->authEnable();
echo "Auth enabled\n";

// 步骤 5: 使用 root 登录获取令牌
$token = $client->authenticate('root', 'root123');
echo "Authenticate: {$token}\n";

// 步骤 6: 设置令牌（后续请求将自动携带）
$client->setToken($token);
echo "Set token\n";

// 现在可以进行需要认证的操作了...

// 禁用认证（需要 root 权限）
$client->authDisable();
echo "Auth disabled\n";

// 清除令牌
$client->clearToken();
echo "Clear token\n";
```

#### 用户管理

```php
// 添加用户
$client->addUser('alice', 'password123');
echo "Add user: alice\n";

// 获取用户信息
$user = $client->getUser('alice');
echo "Get user: " . json_encode($user) . "\n";
// 输出包含: name, roles 等信息

// 修改用户密码
$client->changeUserPassword('alice', 'new-password');
echo "Change user password\n";

// 删除用户
$client->deleteUser('alice');
echo "Delete user\n";

// 列出所有用户
$users = $client->userList();
echo "User list: " . json_encode($users) . "\n";
// 输出: {"users": [{"name": "root"}, {"name": "alice"}]}
```

#### 角色管理

```php
// 创建角色
$client->addRole('readonly');
echo "Add role: readonly\n";

// 获取角色信息（包含权限列表）
$role = $client->getRole('readonly');
echo "Get role: " . json_encode($role) . "\n";

// 删除角色
$client->deleteRole('readonly');
echo "Delete role\n";

// 列出所有角色
$roles = $client->roleList();
echo "Role list: " . json_encode($roles) . "\n";
// 输出: {"roles": [{"name": "root"}, {"name": "readonly"}]}
```

#### 权限管理

```php
use chenbool\Etcd\Client;

// 授予角色权限
// PERMISSION_READ = 0, PERMISSION_WRITE = 1, PERMISSION_READWRITE = 2

// 给 readonly 角色授予对 config/ 前缀的读权限
$client->roleGrantPermission(
    'readonly',
    Client::PERMISSION_READ,
    'config/',
    "config/\xFF"  // 范围结束，表示 config/ 开头的所有键
);
echo "Grant read permission on config/* to readonly\n";

// 给 admin 角色授予对所有键的读写权限
$client->roleGrantPermission(
    'admin',
    Client::PERMISSION_READWRITE,
    "\x00",
    "\xFF"
);
echo "Grant readwrite permission on all keys to admin\n";

// 撤销角色权限
$client->roleRevokePermission('readonly', 'config/', "config/\xFF");
echo "Revoke permission\n";
```

#### 用户角色关联

```php
// 授予角色给用户
$client->grantUserRole('alice', 'readonly');
echo "Grant readonly role to alice\n";

// 撤销用户的角色
$client->revokeUserRole('alice', 'readonly');
echo "Revoke readonly role from alice\n";
```

#### Token 管理

```php
// 用户登录获取 Token
$token = $client->authenticate('alice', 'password123');
echo "Token: {$token}\n";

// 手动设置 Token
$client->setToken($token);

// 获取当前 Token
$currentToken = $client->getToken();
echo "Current token: {$currentToken}\n";

// 清除 Token（匿名访问）
$client->clearToken();
```

---

### 集群管理

#### 成员管理

```php
// 获取集群成员列表
$members = $client->getMembers();
echo "Members: " . json_encode($members) . "\n";
// 输出包含: members（数组，包含 ID、name、peerURLs、clientURLs）

// 添加新成员
$result = $client->addMember(['http://192.168.1.100:2380']);
echo "Add member: " . json_encode($result) . "\n";
// 返回新成员的 ID 和集群当前成员列表

// 移除成员
$client->removeMember(123456789);
echo "Remove member\n";

// 更新成员地址
$client->updateMember(123456789, ['http://192.168.1.101:2380']);
echo "Update member\n";

// 提升 learner 成员为投票成员
$client->promoteMember(123456789);
echo "Promote member\n";

// 迁移成员（更新 peer URL）
$client->moveMember(123456789, 'http://192.168.1.102:2380');
echo "Move member\n";
```

#### Leader 管理

```php
// 获取 Leader 信息
$leader = $client->getLeader();
echo "Leader: " . json_encode($leader) . "\n";
// 输出包含: leader（ID）、raftTerm

// 转移 Leader 到其他成员
$client->moveLeader('8e9e05c52164694d');
echo "Move leader\n";
```

---

### 维护操作

#### 状态与版本

```php
// 获取 Etcd 版本信息
$version = $client->getVersion();
echo "Version: " . json_encode($version) . "\n";
// 输出: {"etcdserver": "3.5.0", "etcdcluster": "3.5.0"}

// 获取服务器状态
$status = $client->getStatus();
echo "Status: " . json_encode($status) . "\n";
// 输出包含: version、dbSize、leader、raftIndex、raftTerm 等
```

#### 快照备份

```php
// 创建快照（保存到 snapshot.db 文件）
$client->snapshot();
echo "Snapshot saved to snapshot.db\n";

// 获取数据哈希（用于数据一致性校验）
$hash = $client->hash();
echo "Hash: " . json_encode($hash) . "\n";
```

#### 碎片整理

```php
// 碎片整理（释放已删除键占用的空间）
// 注意：此操作会锁定数据库，建议在低峰期执行
$client->defragment();
echo "Defragment completed\n";
```

#### 告警管理

```php
// 获取告警列表
$alarms = $client->alarmList();
echo "Alarms: " . json_encode($alarms) . "\n";

// 发送告警
$client->alarmPost('NOSPACE');
echo "Alarm posted: NOSPACE\n";

// 为特定成员发送告警
$client->alarmPost('NOSPACE', '8e9e05c52164694d');
```

#### 降级管理

```php
// 获取降级信息
$downgrades = $client->downgradeList();
echo "Downgrades: " . json_encode($downgrades) . "\n";

// 启用降级（降级到指定版本）
$client->downgradeEnable('3.5.0');
echo "Downgrade enabled to 3.5.0\n";

// 取消降级
$client->downgradeCancel();
echo "Downgrade cancelled\n";
```

---

### 监控与调试

#### 健康检查

```php
// 获取健康状态
$health = $client->getHealth();
echo "Health: " . json_encode($health) . "\n";
// 输出: {"health": "true"} 或 {"health": "false"}
```

#### Metrics

```php
// 获取 Prometheus 格式的监控指标
$metrics = $client->getMetrics();
echo "Metrics: " . substr($metrics, 0, 500) . "...\n";
// 包含: etcd 各种内部指标如 grpc、mvcc、wal 等
```

#### 调试信息

```php
// 获取调试信息
$debugInfo = $client->getDebugInfo();
echo "Debug info: {$debugInfo}\n";

// 获取调试变量
$debugVars = $client->getDebugVars();
echo "Debug vars: " . json_encode($debugVars) . "\n";
```

#### Pprof 性能分析

```php
// 获取 Leader 性能分析
$leaders = $client->getPprofLeaders();
echo "Pprof leaders: " . json_encode($leaders) . "\n";

// 获取 CPU profile（采集 30 秒）
$cpuProfile = $client->getPprofProfile('cpu', 30);
file_put_contents('cpu.pprof', $cpuProfile);
echo "CPU profile saved\n";

// 获取其他类型的 profile
// 可用类型: cpu, heap, goroutine, threadcreate, block, mutex
$heapProfile = $client->getPprofProfile('heap', 30);

// 获取执行追踪数据（采集 30 秒）
$trace = $client->getPprofTrace(30);
file_put_contents('trace.out', $trace);
echo "Trace saved\n";

// 获取符号表
$symbols = $client->getPprofSymbol();
echo "Symbols: " . json_encode($symbols) . "\n";
```

---

### 事务操作

事务支持原子性的 Compare-And-Swap 操作。

```php
// 事务：如果 counter 的值等于 10，则更新为 11，否则获取当前值
$result = $client->transaction(
    // 比较条件
    [
        [
            'key' => 'counter',
            'value' => '10',
            'result' => 'EQUAL'  // 可选: EQUAL, GREATER, LESS, NOT_EQUAL
        ]
    ],
    // 条件满足时执行的操作（success）
    [
        [
            'request_put' => [
                'key' => base64_encode('counter'),
                'value' => base64_encode('11')
            ]
        ]
    ],
    // 条件不满足时执行的操作（failure）
    [
        [
            'request_range' => [
                'key' => base64_encode('counter')
            ]
        ]
    ]
);

echo "Transaction result: " . json_encode($result) . "\n";
// 输出包含: succeeded（true/false）、responses
```

---

### 批量操作

```php
// 批量设置（一次性设置多个键值对）
$results = $client->batchPut([
    'config/database' => 'mysql://localhost:3306',
    'config/redis' => 'redis://localhost:6379',
    'config/cache' => 'memcached://localhost:11211'
]);
echo "Batch put completed: " . count($results) . " items\n";

// 批量设置并绑定租约
$results = $client->batchPut([
    'service/web1' => '192.168.1.10:8080',
    'service/web2' => '192.168.1.11:8080'
], $leaseId);

// 批量获取
$values = $client->batchGet(['config/database', 'config/redis', 'config/cache']);
echo "Batch get: " . json_encode($values) . "\n";
// 输出: {"config/database": "mysql://...", "config/redis": "redis://...", ...}

// 批量删除
$results = $client->batchDelete(['config/database', 'config/redis', 'config/cache']);
echo "Batch delete completed\n";
```

---

## API 参考

### 构造方法

```php
public function __construct(
    string $address = '127.0.0.1:2379',  // 服务器地址，格式: host:port
    int $timeout = 10                    // 请求超时时间（秒）
)
```

### 常量

| 常量 | 值 | 说明 |
|------|-----|------|
| `Client::PERMISSION_READ` | 0 | 只读权限 |
| `Client::PERMISSION_WRITE` | 1 | 只写权限 |
| `Client::PERMISSION_READWRITE` | 2 | 读写权限 |

### 配置方法

| 方法 | 参数 | 返回值 | 说明 |
|------|------|--------|------|
| `setToken(string $token)` | Token 字符串 | void | 设置认证 Token |
| `clearToken()` | - | void | 清除 Token |
| `getToken()` | - | ?string | 获取当前 Token |
| `getHost()` | - | string | 获取主机地址 |
| `getPort()` | - | int | 获取端口号 |
| `getTimeout()` | - | int | 获取超时时间 |
| `setTimeout(int $timeout)` | 超时秒数 | void | 设置超时时间 |
| `setHost(string $host)` | 主机地址 | void | 设置主机地址 |
| `setPort(int $port)` | 端口号 | void | 设置端口号 |
| `getUrl()` | - | string | 获取完整 URL |

---

## 📋 完整示例

详见 [index.php](index.php) 文件，包含所有功能的完整演示：

```bash
php index.php
```

输出示例：
```
Put redis: 127.0.0.1:6379
Put redis with prev_kv
Put redis with lease: 7587894738292371267
Get redis: 127.0.0.1:6579
All keys: city, name, redis
Delete redis
Grant lease: {"ID":7587894738292371267,"TTL":3600}
...
```

---

## 📂 项目结构

```
conn/
├── src/
│   └── Client.php          # Etcd 客户端核心类（800+ 行，完整注释）
├── vendor/                 # Composer 依赖
│   └── chenbool/
│       └── curl/           # HTTP 请求库
├── composer.json           # Composer 配置
├── composer.lock           # 依赖锁定
├── index.php              # 完整使用示例
└── readme.md              # 本文档
```

---

## 🔗 相关链接

- [Etcd 官方文档](https://etcd.io/docs/)
- [Etcd v3 API 参考](https://etcd.io/docs/v3.5/dev-guide/api_reference_v3/)
- [chenbool/curl](https://github.com/chenbool/curl)

---

## 📄 许可证

MIT License

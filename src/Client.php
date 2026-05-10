<?php

namespace chenbool\Etcd\V3;

/**
 * Etcd v3 PHP 客户端
 * 
 * 基于 Etcd v3 HTTP API 实现的 PHP 客户端，支持 KV 操作、租约管理、认证授权、
 * 集群管理、事务操作等完整功能。
 * 
 * @author chenbool
 * @version 1.0.0
 */
class Client
{
    /**
     * 权限类型：只读权限
     * @var int
     */
    public const PERMISSION_READ = 0;
    
    /**
     * 权限类型：只写权限
     * @var int
     */
    public const PERMISSION_WRITE = 1;
    
    /**
     * 权限类型：读写权限
     * @var int
     */
    public const PERMISSION_READWRITE = 2;

    /**
     * Etcd 服务器主机地址
     * @var string
     */
    private string $host;
    
    /**
     * Etcd 服务器端口
     * @var int
     */
    private int $port;
    
    /**
     * 请求超时时间（秒）
     * @var int
     */
    private int $timeout;
    
    /**
     * 认证令牌
     * @var string|null
     */
    private ?string $token = null;

    /**
     * 构造函数
     * 
     * @param string $address Etcd 服务器地址，格式为 "host:port" 或 "host"
     * @param int $timeout 请求超时时间（秒），默认 10 秒
     */
    public function __construct(string $address = '127.0.0.1:2379', int $timeout = 10)
    {
        // 解析地址，分离主机和端口
        if (strpos($address, ':') !== false) {
            [$this->host, $port] = explode(':', $address, 2);
            $this->port = (int)$port;
        } else {
            $this->host = $address;
            $this->port = 2379;
        }
        $this->timeout = $timeout;
    }

    /**
     * 发送 HTTP 请求到 Etcd API
     * 
     * 内部方法，用于封装所有 API 调用。自动处理认证令牌、JSON 编码和错误处理。
     * 
     * @param string $endpoint API 端点路径（不包含 /v3/ 前缀）
     * @param array $data 请求数据
     * @return array 解析后的 JSON 响应
     * @throws \Exception 请求失败时抛出异常
     */
    private function request(string $endpoint, array $data = []): array
    {
        // 构建完整 URL
        $url = "http://{$this->host}:{$this->port}/v3/{$endpoint}";
        
        // 设置请求头，添加认证令牌（如果存在）
        $headers = [];
        if ($this->token !== null) {
            $headers['Authorization'] = $this->token;
        }

        // 空数组转为空对象 {}，避免 etcd 解析错误
        $body = empty($data) ? '{}' : json_encode($data);

        // 使用 cURL 发送 POST 请求
        $response = \chenbool\Curl::init()
            ->set('CURLOPT_HTTPHEADER', $headers)
            ->set('CURLOPT_TIMEOUT', $this->timeout)
            ->set('CURLOPT_CONNECTTIMEOUT', $this->timeout)
            ->post($body)
            ->url($url);

        // 检查 cURL 错误
        if ($response->error()) {
            throw new \Exception("HTTP request failed: " . $response->message());
        }

        // 检查 HTTP 状态码
        $info = $response->info();
        if ($info['http_code'] >= 400) {
            throw new \Exception("HTTP request failed: {$info['http_code']} - {$response->data()}");
        }

        return json_decode($response->data(), true);
    }

    /**
     * 设置认证令牌
     * 
     * 用于设置从 authenticate() 方法获取的令牌
     * 
     * @param string $token 认证令牌
     */
    public function setToken(string $token): void
    {
        $this->token = $token;
    }

    /**
     * 清除认证令牌
     * 
     * 清除后后续请求将不再包含认证信息
     */
    public function clearToken(): void
    {
        $this->token = null;
    }

    /**
     * 获取当前认证令牌
     * 
     * @return string|null 当前令牌或 null
     */
    public function getToken(): ?string
    {
        return $this->token;
    }

    /**
     * 获取服务器主机地址
     * 
     * @return string 主机地址
     */
    public function getHost(): string
    {
        return $this->host;
    }

    /**
     * 获取服务器端口
     * 
     * @return int 端口号
     */
    public function getPort(): int
    {
        return $this->port;
    }

    /**
     * 获取请求超时时间
     * 
     * @return int 超时时间（秒）
     */
    public function getTimeout(): int
    {
        return $this->timeout;
    }

    /**
     * 设置请求超时时间
     * 
     * @param int $timeout 超时时间（秒）
     */
    public function setTimeout(int $timeout): void
    {
        $this->timeout = $timeout;
    }

    /**
     * 设置服务器主机地址
     * 
     * @param string $host 主机地址
     */
    public function setHost(string $host): void
    {
        $this->host = $host;
    }

    /**
     * 设置服务器端口
     * 
     * @param int $port 端口号
     */
    public function setPort(int $port): void
    {
        $this->port = $port;
    }

    /**
     * 获取 Etcd 服务器 URL
     * 
     * @return string 完整 URL（如 http://127.0.0.1:2379）
     */
    public function getUrl(): string
    {
        return "http://{$this->host}:{$this->port}";
    }

    /**
     * 获取 Etcd 版本信息
     * 
     * @return array 包含 etcdserver、etcdcluster 等版本信息
     */
    public function getVersion(): array
    {
        $url = "http://{$this->host}:{$this->port}/version";
        $response = \chenbool\Curl::init()
            ->set('CURLOPT_TIMEOUT', $this->timeout)
            ->set('CURLOPT_CONNECTTIMEOUT', $this->timeout)
            ->url($url);
        return json_decode($response->data(), true);
    }

    /**
     * 获取服务器状态
     * 
     * 返回 Etcd 服务器的详细状态信息
     * 
     * @return array 状态信息数组
     */
    public function getStatus(): array
    {
        return $this->request('maintenance/status');
    }

    /**
     * 获取 Leader 节点信息
     * 
     * @return array Leader 信息数组
     */
    public function getLeader(): array
    {
        return $this->request('maintenance/leader');
    }

    /**
     * 创建快照
     * 
     * 备份当前 Etcd 数据到 snapshot.db 文件
     */
    public function snapshot(): void
    {
        $url = "http://{$this->host}:{$this->port}/v3/maintenance/snapshot";
        $response = \chenbool\Curl::init()
            ->set('CURLOPT_TIMEOUT', $this->timeout)
            ->set('CURLOPT_CONNECTTIMEOUT', $this->timeout)
            ->set('CURLOPT_FILE', fopen('snapshot.db', 'w'))
            ->url($url);
    }

    /**
     * 计算数据哈希
     * 
     * 用于验证数据一致性
     * 
     * @return array 哈希信息
     */
    public function hash(): array
    {
        return $this->request('maintenance/hash');
    }

    /**
     * 转移 Leader 角色
     * 
     * @param string $leaderId 目标 Leader ID
     * @return array 操作结果
     */
    public function moveLeader(string $leaderId): array
    {
        return $this->request('maintenance/transfer-leadership', ['leader' => $leaderId]);
    }

    /**
     * 获取集群成员列表
     * 
     * @return array 成员列表
     */
    public function getMembers(): array
    {
        return $this->request('cluster/member-list');
    }

    /**
     * 添加集群成员
     * 
     * @param array $peerURLs 节点地址列表
     * @return array 操作结果
     */
    public function addMember(array $peerURLs): array
    {
        return $this->request('cluster/member-add', ['peerURLs' => $peerURLs]);
    }

    /**
     * 移除集群成员
     * 
     * @param int $id 成员 ID
     * @return array 操作结果
     */
    public function removeMember(int $id): array
    {
        return $this->request('cluster/member-remove', ['ID' => $id]);
    }

    /**
     * 更新集群成员信息
     * 
     * @param int $id 成员 ID
     * @param array $peerURLs 新的节点地址列表
     * @return array 操作结果
     */
    public function updateMember(int $id, array $peerURLs): array
    {
        return $this->request('cluster/member-update', ['ID' => $id, 'peerURLs' => $peerURLs]);
    }

    /**
     * 提升成员为投票成员
     * 
     * @param int $id 成员 ID
     * @return array 操作结果
     */
    public function promoteMember(int $id): array
    {
        return $this->request('cluster/member-promote', ['ID' => $id]);
    }

    /**
     * 迁移成员
     * 
     * @param int $id 成员 ID
     * @param string $peerURL 新的节点地址
     * @return array 操作结果
     */
    public function moveMember(int $id, string $peerURL): array
    {
        return $this->request('cluster/member-update', ['ID' => $id, 'peerURLs' => [$peerURL]]);
    }

    /**
     * 整理存储空间
     * 
     * 释放已删除键占用的空间
     * 
     * @return array 操作结果
     */
    public function defragment(): array
    {
        return $this->request('maintenance/defragment');
    }

    /**
     * 获取告警列表
     * 
     * @return array 告警信息数组
     */
    public function alarmList(): array
    {
        return $this->request('maintenance/alarms');
    }

    /**
     * 处理告警
     * 
     * @param string $alarm 告警类型（如 NO_SPACE）
     * @param string|null $memberID 成员 ID，null 表示所有成员
     * @return array 操作结果
     */
    public function alarmPost(string $alarm, ?string $memberID = null): array
    {
        $data = ['alarm' => $alarm];
        if ($memberID !== null) {
            $data['memberID'] = $memberID;
        }
        return $this->request('maintenance/alarm', $data);
    }

    /**
     * 获取降级信息
     * 
     * @return array 降级信息
     */
    public function downgradeList(): array
    {
        return $this->request('maintenance/downgrade');
    }

    /**
     * 启用降级模式
     * 
     * @param string $version 目标版本
     * @return array 操作结果
     */
    public function downgradeEnable(string $version): array
    {
        return $this->request('maintenance/downgrade', ['action' => 'enable', 'version' => $version]);
    }

    /**
     * 取消降级模式
     * 
     * @return array 操作结果
     */
    public function downgradeCancel(): array
    {
        return $this->request('maintenance/downgrade', ['action' => 'cancel']);
    }

    /**
     * 设置键值
     * 
     * 在 Etcd 中存储键值对，支持租约、返回旧值等选项
     * 
     * @param string $key 键名
     * @param string $value 键值
     * @param array $options 可选参数：
     *   - lease: int 租约 ID，设置后键将在租约过期后自动删除
     *   - prev_kv: bool 是否返回旧值
     *   - ignore_value: bool 忽略值（仅更新租约）
     *   - ignore_lease: bool 忽略租约
     * @return array 操作结果
     */
    public function put(string $key, string $value, array $options = []): array
    {
        $data = [
            'key' => base64_encode($key),
            'value' => base64_encode($value),
        ];

        if (!empty($options['lease'])) {
            $data['lease'] = (string)$options['lease'];
        }

        if (!empty($options['prev_kv'])) {
            $data['prev_kv'] = true;
        }

        if (!empty($options['ignore_value'])) {
            $data['ignore_value'] = true;
        }

        if (!empty($options['ignore_lease'])) {
            $data['ignore_lease'] = true;
        }

        return $this->request('kv/put', $data);
    }

    /**
     * 获取键值
     * 
     * 根据键名获取对应的值
     * 
     * @param string $key 键名
     * @param array $options 可选参数：
     *   - revision: int 指定版本号
     *   - limit: int 返回数量限制
     *   - count_only: bool 仅返回计数
     *   - sort_target: string 排序目标
     *   - sort_order: string 排序顺序
     *   - range_end: string 范围结束键（用于范围查询）
     * @return string|null 键值或 null（键不存在时）
     */
    public function get(string $key, array $options = []): ?string
    {
        $data = [
            'key' => base64_encode($key),
        ];

        if (!empty($options['revision'])) {
            $data['revision'] = $options['revision'];
        }

        if (!empty($options['limit'])) {
            $data['limit'] = $options['limit'];
        }

        if (!empty($options['count_only'])) {
            $data['count_only'] = true;
        }

        if (!empty($options['sort_target'])) {
            $data['sort_target'] = $options['sort_target'];
        }

        if (!empty($options['sort_order'])) {
            $data['sort_order'] = $options['sort_order'];
        }

        if (!empty($options['range_end'])) {
            $data['range_end'] = base64_encode($options['range_end']);
        }

        $result = $this->request('kv/range', $data);

        if (empty($result['kvs'])) {
            return null;
        }

        return base64_decode($result['kvs'][0]['value']);
    }

    /**
     * 获取键值（原始格式）
     * 
     * 返回完整的 API 响应，包含元数据
     * 
     * @param string $key 键名
     * @param array $options 与 get() 方法相同的可选参数
     * @return array 完整的响应数据
     */
    public function getRaw(string $key, array $options = []): array
    {
        $data = [
            'key' => base64_encode($key),
        ];

        if (!empty($options['revision'])) {
            $data['revision'] = $options['revision'];
        }

        if (!empty($options['limit'])) {
            $data['limit'] = $options['limit'];
        }

        if (!empty($options['count_only'])) {
            $data['count_only'] = true;
        }

        if (!empty($options['sort_target'])) {
            $data['sort_target'] = $options['sort_target'];
        }

        if (!empty($options['sort_order'])) {
            $data['sort_order'] = $options['sort_order'];
        }

        if (!empty($options['range_end'])) {
            $data['range_end'] = base64_encode($options['range_end']);
        }

        return $this->request('kv/range', $data);
    }

    /**
     * 获取所有键
     * 
     * 返回 Etcd 中所有的键名列表
     * 
     * @param array $options 可选参数：
     *   - revision: int 指定版本号
     *   - limit: int 返回数量限制
     *   - count_only: bool 仅返回计数
     *   - sort_target: string 排序目标
     *   - sort_order: string 排序顺序
     * @return array 键名列表
     */
    public function getAllKeys(array $options = []): array
    {
        $data = [
            'key' => base64_encode("\x00"),
            'range_end' => base64_encode("\xFF"),
        ];

        if (!empty($options['revision'])) {
            $data['revision'] = $options['revision'];
        }

        if (!empty($options['limit'])) {
            $data['limit'] = $options['limit'];
        }

        if (!empty($options['count_only'])) {
            $data['count_only'] = true;
        }

        if (!empty($options['sort_target'])) {
            $data['sort_target'] = $options['sort_target'];
        }

        if (!empty($options['sort_order'])) {
            $data['sort_order'] = $options['sort_order'];
        }

        $result = $this->request('kv/range', $data);

        if (empty($result['kvs'])) {
            return [];
        }

        $keys = [];
        foreach ($result['kvs'] as $kv) {
            $keys[] = base64_decode($kv['key']);
        }

        return $keys;
    }

    /**
     * 删除键
     * 
     * 删除指定的键或键范围
     * 
     * @param string $key 键名
     * @param array $options 可选参数：
     *   - prev_kv: bool 是否返回被删除的旧值
     *   - range_end: string 范围结束键（用于范围删除）
     * @return array 操作结果
     */
    public function del(string $key, array $options = []): array
    {
        $data = [
            'key' => base64_encode($key),
        ];

        if (!empty($options['prev_kv'])) {
            $data['prev_kv'] = true;
        }

        if (!empty($options['range_end'])) {
            $data['range_end'] = base64_encode($options['range_end']);
        }

        return $this->request('kv/deleterange', $data);
    }

    /**
     * 压缩历史版本
     * 
     * 删除指定版本之前的所有历史数据，释放空间
     * 
     * @param int $revision 要压缩到的版本号
     * @param bool $physical 是否物理删除（默认只删除元数据）
     * @return array 操作结果
     */
    public function compaction(int $revision, bool $physical = false): array
    {
        return $this->request('kv/compaction', [
            'revision' => $revision,
            'physical' => $physical,
        ]);
    }

    /**
     * 监听键变化（流式）
     * 
     * 持续监听指定键的变化，通过回调函数处理事件
     * 
     * @param string $key 要监听的键
     * @param callable $callback 回调函数，接收变化事件数组
     * @param array $options 可选参数：
     *   - revision: int 从指定版本开始监听
     *   - progress_notify: bool 是否接收进度通知
     *   - filters: array 过滤器
     *   - prev_kv: bool 是否包含旧值
     *   - watch_id: int 监听 ID
     */
    public function watch(string $key, callable $callback, array $options = []): void
    {
        $url = "http://{$this->host}:{$this->port}/v3/watch";
        
        $headers = ['Content-Type' => 'application/json'];
        if ($this->token !== null) {
            $headers['Authorization'] = $this->token;
        }

        $data = [
            'create_request' => [
                'key' => base64_encode($key),
            ],
        ];

        if (!empty($options['revision'])) {
            $data['create_request']['revision'] = $options['revision'];
        }

        if (!empty($options['progress_notify'])) {
            $data['create_request']['progress_notify'] = true;
        }

        if (!empty($options['filters'])) {
            $data['create_request']['filters'] = $options['filters'];
        }

        if (!empty($options['prev_kv'])) {
            $data['create_request']['prev_kv'] = true;
        }

        if (!empty($options['watch_id'])) {
            $data['create_request']['watch_id'] = $options['watch_id'];
        }

        $stream = \chenbool\Curl::init()
            ->set('CURLOPT_HTTPHEADER', $headers)
            ->set('CURLOPT_TIMEOUT', $this->timeout)
            ->set('CURLOPT_CONNECTTIMEOUT', $this->timeout)
            ->post(json_encode($data))
            ->url($url);

        $body = $stream->data();
        $lines = explode("\n", $body);
        
        foreach ($lines as $line) {
            if (empty(trim($line))) {
                continue;
            }
            $data = json_decode($line, true);
            if ($data && isset($data['result'])) {
                $callback($data['result']);
            }
        }
    }

    /**
     * 监听键变化（单次）
     * 
     * 获取一次监听响应
     * 
     * @param string $key 要监听的键
     * @param array $options 与 watch() 相同的可选参数
     * @return array|null 监听结果或 null
     */
    public function watchOnce(string $key, array $options = []): ?array
    {
        $url = "http://{$this->host}:{$this->port}/v3/watch";
        
        $headers = ['Content-Type' => 'application/json'];
        if ($this->token !== null) {
            $headers['Authorization'] = $this->token;
        }

        $data = [
            'create_request' => [
                'key' => base64_encode($key),
            ],
        ];

        if (!empty($options['revision'])) {
            $data['create_request']['revision'] = $options['revision'];
        }

        if (!empty($options['progress_notify'])) {
            $data['create_request']['progress_notify'] = true;
        }

        if (!empty($options['filters'])) {
            $data['create_request']['filters'] = $options['filters'];
        }

        if (!empty($options['prev_kv'])) {
            $data['create_request']['prev_kv'] = true;
        }

        $result = $this->request('watch', $data);
        
        if (!empty($result['watch_id'])) {
            return $result;
        }
        
        return null;
    }

    /**
     * 授予租约
     * 
     * 创建一个租约，用于设置键的生存时间
     * 
     * @param int $ttl 生存时间（秒）
     * @param int|null $id 指定租约 ID，null 则由服务器自动生成
     * @return array 包含租约 ID 的信息
     */
    public function grant(int $ttl, ?int $id = null): array
    {
        $data = ['TTL' => $ttl];
        
        if ($id !== null) {
            $data['ID'] = $id;
        }

        return $this->request('lease/grant', $data);
    }

    /**
     * 撤销租约
     * 
     * 删除租约并清理关联的所有键
     * 
     * @param int $leaseId 租约 ID
     * @return array 操作结果
     */
    public function revoke(int $leaseId): array
    {
        return $this->request('lease/revoke', ['ID' => $leaseId]);
    }

    /**
     * 续租
     * 
     * 续租以保持租约活跃
     * 
     * @param int $leaseId 租约 ID
     * @return array 操作结果
     * @throws \Exception 续租失败时抛出异常
     */
    public function keepAlive(int $leaseId): array
    {
        $url = "http://{$this->host}:{$this->port}/v3/lease/keepalive";
        
        $headers = [];
        if ($this->token !== null) {
            $headers['Authorization'] = $this->token;
        }

        $response = \chenbool\Curl::init()
            ->set('CURLOPT_HTTPHEADER', $headers)
            ->set('CURLOPT_TIMEOUT', $this->timeout)
            ->set('CURLOPT_CONNECTTIMEOUT', $this->timeout)
            ->post(json_encode(['ID' => $leaseId]))
            ->url($url);

        if ($response->error()) {
            throw new \Exception("Lease keepalive failed: " . $response->message());
        }

        return json_decode($response->data(), true);
    }

    /**
     * 查询租约信息
     * 
     * 获取租约的剩余 TTL 和关联的键
     * 
     * @param int $leaseId 租约 ID
     * @param bool $keys 是否返回关联的键列表
     * @return array 租约信息
     */
    public function timeToLive(int $leaseId, bool $keys = false): array
    {
        return $this->request('lease/timetolive', [
            'ID' => $leaseId,
            'keys' => $keys,
        ]);
    }

    /**
     * 列出所有租约
     * 
     * @return array 租约 ID 列表
     */
    public function leases(): array
    {
        $result = $this->request('lease/leases', []);
        
        if (empty($result['leases'])) {
            return [];
        }
        
        return array_map(function($lease) {
            return $lease['ID'];
        }, $result['leases']);
    }

    /**
     * 启用认证
     * 
     * 开启 Etcd 的认证功能（需要先创建 root 用户）
     * 
     * @return array 操作结果
     */
    public function authEnable(): array
    {
        return $this->request('auth/enable');
    }

    /**
     * 禁用认证
     * 
     * 关闭 Etcd 的认证功能（需要 root 权限）
     * 
     * @return array 操作结果
     */
    public function authDisable(): array
    {
        return $this->request('auth/disable');
    }

    /**
     * 用户认证登录
     * 
     * 使用用户名和密码进行认证，成功后自动保存令牌
     * 
     * @param string $username 用户名
     * @param string $password 密码
     * @return string 认证令牌
     */
    public function authenticate(string $username, string $password): string
    {
        $result = $this->request('auth/authenticate', [
            'name' => $username,
            'password' => $password,
        ]);

        if (isset($result['token'])) {
            $this->token = $result['token'];
        }

        return $result['token'] ?? '';
    }

    /**
     * 添加角色
     * 
     * 创建一个新的角色
     * 
     * @param string $name 角色名称
     * @return array 操作结果
     */
    public function addRole(string $name): array
    {
        return $this->request('auth/role/add', ['name' => $name]);
    }

    /**
     * 获取角色信息
     * 
     * @param string $name 角色名称
     * @return array 角色信息
     */
    public function getRole(string $name): array
    {
        return $this->request('auth/role/get', ['name' => $name]);
    }

    /**
     * 删除角色
     * 
     * @param string $name 角色名称
     * @return array 操作结果
     */
    public function deleteRole(string $name): array
    {
        return $this->request('auth/role/delete', ['name' => $name]);
    }

    /**
     * 列出所有角色
     * 
     * @return array 角色列表
     */
    public function roleList(): array
    {
        return $this->request('auth/role/list');
    }

    /**
     * 授予角色权限
     * 
     * 给角色分配对指定键的访问权限
     * 
     * @param string $name 角色名称
     * @param int $permType 权限类型（PERMISSION_READ/PERMISSION_WRITE/PERMISSION_READWRITE）
     * @param string $key 键名
     * @param string|null $rangeEnd 范围结束键
     * @return array 操作结果
     */
    public function roleGrantPermission(string $name, int $permType, string $key, ?string $rangeEnd = null): array
    {
        $perm = [
            'permType' => $permType,
            'key' => base64_encode($key),
        ];

        if ($rangeEnd !== null) {
            $perm['range_end'] = base64_encode($rangeEnd);
        }

        return $this->request('auth/role/grant', [
            'name' => $name,
            'perm' => $perm,
        ]);
    }

    /**
     * 撤销角色权限
     * 
     * 移除角色对指定键的访问权限
     * 
     * @param string $name 角色名称
     * @param string $key 键名
     * @param string|null $rangeEnd 范围结束键
     * @return array 操作结果
     */
    public function roleRevokePermission(string $name, string $key, ?string $rangeEnd = null): array
    {
        $data = [
            'name' => $name,
            'key' => base64_encode($key),
        ];

        if ($rangeEnd !== null) {
            $data['range_end'] = base64_encode($rangeEnd);
        }

        return $this->request('auth/role/revoke', $data);
    }

    /**
     * 添加用户
     * 
     * 创建一个新用户
     * 
     * @param string $username 用户名
     * @param string $password 密码
     * @return array 操作结果
     */
    public function addUser(string $username, string $password): array
    {
        return $this->request('auth/user/add', [
            'name' => $username,
            'password' => $password,
        ]);
    }

    /**
     * 获取用户信息
     * 
     * @param string $username 用户名
     * @return array 用户信息
     */
    public function getUser(string $username): array
    {
        return $this->request('auth/user/get', ['name' => $username]);
    }

    /**
     * 删除用户
     * 
     * @param string $username 用户名
     * @return array 操作结果
     */
    public function deleteUser(string $username): array
    {
        return $this->request('auth/user/delete', ['name' => $username]);
    }

    /**
     * 列出所有用户
     * 
     * @return array 用户列表
     */
    public function userList(): array
    {
        return $this->request('auth/user/list');
    }

    /**
     * 修改用户密码
     * 
     * @param string $username 用户名
     * @param string $password 新密码
     * @return array 操作结果
     */
    public function changeUserPassword(string $username, string $password): array
    {
        return $this->request('auth/user/change-password', [
            'name' => $username,
            'password' => $password,
        ]);
    }

    /**
     * 授予用户角色
     * 
     * 将角色分配给用户
     * 
     * @param string $username 用户名
     * @param string $role 角色名称
     * @return array 操作结果
     */
    public function grantUserRole(string $username, string $role): array
    {
        return $this->request('auth/user/grant', [
            'user' => $username,
            'role' => $role,
        ]);
    }

    /**
     * 撤销用户角色
     * 
     * 从用户身上移除角色
     * 
     * @param string $username 用户名
     * @param string $role 角色名称
     * @return array 操作结果
     */
    public function revokeUserRole(string $username, string $role): array
    {
        return $this->request('auth/user/revoke', [
            'user' => $username,
            'role' => $role,
        ]);
    }

    /**
     * 修改用户密码（别名）
     * 
     * @see changeUserPassword()
     */
    public function userChangePassword(string $username, string $password): array
    {
        return $this->changeUserPassword($username, $password);
    }

    /**
     * 授予用户角色（别名）
     * 
     * @see grantUserRole()
     */
    public function userGrantRole(string $username, string $role): array
    {
        return $this->grantUserRole($username, $role);
    }

    /**
     * 撤销用户角色（别名）
     * 
     * @see revokeUserRole()
     */
    public function userRevokeRole(string $username, string $role): array
    {
        return $this->revokeUserRole($username, $role);
    }

    /**
     * 授予角色权限（别名）
     * 
     * @see roleGrantPermission()
     */
    public function rolePermissionGrant(string $rolename, int $permType, string $key, ?string $rangeEnd = null): array
    {
        return $this->roleGrantPermission($rolename, $permType, $key, $rangeEnd);
    }

    /**
     * 撤销角色权限（别名）
     * 
     * @see roleRevokePermission()
     */
    public function rolePermissionRevoke(string $rolename, string $key, ?string $rangeEnd = null): array
    {
        return $this->roleRevokePermission($rolename, $key, $rangeEnd);
    }

    /**
     * 授予角色权限（别名）
     * 
     * @see roleGrantPermission()
     */
    public function grantRolePermission(string $rolename, int $permType, string $key, ?string $rangeEnd = null): array
    {
        return $this->roleGrantPermission($rolename, $permType, $key, $rangeEnd);
    }

    /**
     * 撤销角色权限（别名）
     * 
     * @see roleRevokePermission()
     */
    public function revokeRolePermission(string $rolename, string $key, ?string $rangeEnd = null): array
    {
        return $this->roleRevokePermission($rolename, $key, $rangeEnd);
    }

    /**
     * 设置权限
     * 
     * 为 root 角色设置权限
     * 
     * @param int $permType 权限类型
     * @param string $key 键名
     * @param string|null $rangeEnd 范围结束键
     * @return array 操作结果
     */
    public function setPermission(int $permType, string $key, ?string $rangeEnd = null): array
    {
        return $this->roleGrantPermission('root', $permType, $key, $rangeEnd);
    }

    /**
     * 撤销权限
     * 
     * 撤销 root 角色的权限
     * 
     * @param string $key 键名
     * @param string|null $rangeEnd 范围结束键
     * @return array 操作结果
     */
    public function revokePermission(string $key, ?string $rangeEnd = null): array
    {
        return $this->roleRevokePermission('root', $key, $rangeEnd);
    }

    /**
     * 添加角色（别名）
     * 
     * @see addRole()
     */
    public function authRoleAdd(string $name): array
    {
        return $this->addRole($name);
    }

    /**
     * 获取角色信息（别名）
     * 
     * @see getRole()
     */
    public function authRoleGet(string $name): array
    {
        return $this->getRole($name);
    }

    /**
     * 删除角色（别名）
     * 
     * @see deleteRole()
     */
    public function authRoleDelete(string $name): array
    {
        return $this->deleteRole($name);
    }

    /**
     * 列出所有角色（别名）
     * 
     * @see roleList()
     */
    public function authRoleList(): array
    {
        return $this->roleList();
    }

    /**
     * 添加用户（别名）
     * 
     * @see addUser()
     */
    public function authUserAdd(string $username, string $password): array
    {
        return $this->addUser($username, $password);
    }

    /**
     * 获取用户信息（别名）
     * 
     * @see getUser()
     */
    public function authUserGet(string $username): array
    {
        return $this->getUser($username);
    }

    /**
     * 删除用户（别名）
     * 
     * @see deleteUser()
     */
    public function authUserDelete(string $username): array
    {
        return $this->deleteUser($username);
    }

    /**
     * 列出所有用户（别名）
     * 
     * @see userList()
     */
    public function authUserList(): array
    {
        return $this->userList();
    }

    /**
     * 授予用户角色（别名）
     * 
     * @see grantUserRole()
     */
    public function authUserGrantRole(string $username, string $role): array
    {
        return $this->grantUserRole($username, $role);
    }

    /**
     * 撤销用户角色（别名）
     * 
     * @see revokeUserRole()
     */
    public function authUserRevokeRole(string $username, string $role): array
    {
        return $this->revokeUserRole($username, $role);
    }

    /**
     * 授予角色权限（别名）
     * 
     * @see roleGrantPermission()
     */
    public function authRoleGrantPermission(string $rolename, int $permType, string $key, ?string $rangeEnd = null): array
    {
        return $this->roleGrantPermission($rolename, $permType, $key, $rangeEnd);
    }

    /**
     * 撤销角色权限（别名）
     * 
     * @see roleRevokePermission()
     */
    public function authRoleRevokePermission(string $rolename, string $key, ?string $rangeEnd = null): array
    {
        return $this->roleRevokePermission($rolename, $key, $rangeEnd);
    }

    /**
     * 获取健康状态
     * 
     * @return array 健康状态信息
     */
    public function getHealth(): array
    {
        $url = "http://{$this->host}:{$this->port}/health";
        $response = \chenbool\Curl::init()
            ->set('CURLOPT_TIMEOUT', $this->timeout)
            ->set('CURLOPT_CONNECTTIMEOUT', $this->timeout)
            ->url($url);
        return json_decode($response->data(), true);
    }

    /**
     * 获取监控指标
     * 
     * @return string Prometheus 格式的指标数据
     */
    public function getMetrics(): string
    {
        $url = "http://{$this->host}:{$this->port}/metrics";
        $response = \chenbool\Curl::init()
            ->set('CURLOPT_TIMEOUT', $this->timeout)
            ->set('CURLOPT_CONNECTTIMEOUT', $this->timeout)
            ->url($url);
        return $response->data();
    }

    /**
     * 获取调试信息
     * 
     * @return string 调试信息
     */
    public function getDebugInfo(): string
    {
        $url = "http://{$this->host}:{$this->port}/debug/info";
        $response = \chenbool\Curl::init()
            ->set('CURLOPT_TIMEOUT', $this->timeout)
            ->set('CURLOPT_CONNECTTIMEOUT', $this->timeout)
            ->url($url);
        return $response->data();
    }

    /**
     * 获取调试变量
     * 
     * @return array 调试变量数组
     */
    public function getDebugVars(): array
    {
        $url = "http://{$this->host}:{$this->port}/debug/vars";
        $response = \chenbool\Curl::init()
            ->set('CURLOPT_TIMEOUT', $this->timeout)
            ->set('CURLOPT_CONNECTTIMEOUT', $this->timeout)
            ->url($url);
        return json_decode($response->data(), true);
    }

    /**
     * 获取 Leader 性能分析
     * 
     * @return array 性能分析数据
     */
    public function getPprofLeaders(): array
    {
        $url = "http://{$this->host}:{$this->port}/debug/pprof/leader";
        $response = \chenbool\Curl::init()
            ->set('CURLOPT_TIMEOUT', $this->timeout)
            ->set('CURLOPT_CONNECTTIMEOUT', $this->timeout)
            ->url($url);
        return json_decode($response->data(), true);
    }

    /**
     * 获取性能分析数据
     * 
     * @param string $type 分析类型（cpu、memory、goroutine 等）
     * @param int $seconds 采集时间（秒）
     * @return string 性能分析数据
     */
    public function getPprofProfile(string $type = 'cpu', int $seconds = 30): string
    {
        $url = "http://{$this->host}:{$this->port}/debug/pprof/{$type}?seconds={$seconds}";
        $response = \chenbool\Curl::init()
            ->set('CURLOPT_TIMEOUT', $this->timeout + $seconds)
            ->set('CURLOPT_CONNECTTIMEOUT', $this->timeout + $seconds)
            ->url($url);
        return $response->data();
    }

    /**
     * 获取执行追踪数据
     * 
     * @param int $seconds 采集时间（秒）
     * @return string 追踪数据
     */
    public function getPprofTrace(int $seconds = 30): string
    {
        $url = "http://{$this->host}:{$this->port}/debug/pprof/trace?seconds={$seconds}";
        $response = \chenbool\Curl::init()
            ->set('CURLOPT_TIMEOUT', $this->timeout + $seconds)
            ->set('CURLOPT_CONNECTTIMEOUT', $this->timeout + $seconds)
            ->url($url);
        return $response->data();
    }

    /**
     * 获取符号表
     * 
     * @return array 符号表数据
     */
    public function getPprofSymbol(): array
    {
        $url = "http://{$this->host}:{$this->port}/debug/pprof/symbol";
        $response = \chenbool\Curl::init()
            ->set('CURLOPT_TIMEOUT', $this->timeout)
            ->set('CURLOPT_CONNECTTIMEOUT', $this->timeout)
            ->url($url);
        return json_decode($response->data(), true);
    }

    /**
     * 事务操作
     * 
     * 执行原子性多操作事务
     * 
     * @param array $compares 条件比较数组，每个元素包含：
     *   - key: 要比较的键
     *   - value: 比较的值
     *   - result: 比较结果（EQUAL、GREATER、LESS、NOT_EQUAL）
     * @param array $successOps 条件满足时执行的操作
     * @param array $failureOps 条件不满足时执行的操作
     * @return array 事务执行结果
     */
    public function transaction(array $compares, array $successOps = [], array $failureOps = []): array
    {
        $data = [
            'compare' => [],
            'success' => $successOps,
            'failure' => $failureOps,
        ];

        foreach ($compares as $compare) {
            $data['compare'][] = [
                'result' => $compare['result'] ?? 'EQUAL',
                'target' => 'VALUE',
                'key' => base64_encode($compare['key']),
                'value' => base64_encode($compare['value']),
            ];
        }

        return $this->request('kv/txn', $data);
    }

    /**
     * 批量设置键值
     * 
     * 一次性设置多个键值对
     * 
     * @param array $items 键值对数组 ['key1' => 'value1', 'key2' => 'value2']
     * @param int|null $lease 可选的租约 ID
     * @return array 每个键的设置结果
     */
    public function batchPut(array $items, ?int $lease = null): array
    {
        $results = [];
        foreach ($items as $key => $value) {
            $results[] = $this->put($key, $value, ['lease' => $lease]);
        }
        return $results;
    }

    /**
     * 批量获取键值
     * 
     * 一次性获取多个键的值
     * 
     * @param array $keys 键名数组
     * @return array 键值对数组 ['key1' => 'value1', 'key2' => 'value2']
     */
    public function batchGet(array $keys): array
    {
        $results = [];
        foreach ($keys as $key) {
            $results[$key] = $this->get($key);
        }
        return $results;
    }

    /**
     * 批量删除键
     * 
     * 一次性删除多个键
     * 
     * @param array $keys 键名数组
     * @return array 每个键的删除结果
     */
    public function batchDelete(array $keys): array
    {
        $results = [];
        foreach ($keys as $key) {
            $results[] = $this->del($key);
        }
        return $results;
    }
}

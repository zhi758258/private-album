# 私藏

内部相册 + 留言板 + 备忘录。使用独立部署的 MySQL 数据库，支持卡密注册。

## 功能

- 登录后才能看相册和留言
- 管理员可添加用户，也可开放注册（可要求卡密/邀请码）
- 管理员后台批量生成卡密，卡密一次性使用、永久时效
- 用户上传 jpg / png / gif / webp，最大 8MB
- 相册墙（滑动/平铺视图、点赞、灯箱大图预览）；照片是否公开到相册墙由本人选择，默认不公开
- 各用户相册互相隔离，仅管理员可查看全部用户的相册
- 管理员可删除照片（带确认弹窗）
- 管理员可自定义所有导航菜单名称
- 管理员可使用文件管理：上传/下载任意格式文件，保存在 `wenjian123/`
- 留言板（含首页留言）显示每位用户的头像，点击头像可加好友或发私信
- 留言板与好友私信支持 QQ / 微信 / 抖音表情，以及打字联想搜索网络表情
- 定位分享：留言、回复与好友私信可附带当前位置，显示地名并可点击打开地图
- 语音消息：留言、回复与好友私信支持录制并发送语音（最长 60 秒），气泡内直接播放
- 拍照 / 录像：在留言和好友私信里调用摄像头拍照或录像（视频最长 60 秒）并作为消息发送
- 视频通话：好友之间可发起 1 对 1 网页视频通话，支持静音、关闭摄像头与挂断
- 用户空间（QQ空间式）：心情说说与日志，默认好友可见，也可设为仅自己可见
- 好友功能：搜索用户、发送/处理好友请求，好友之间可实时对话
- 消息提醒：顶部铃铛汇总留言板新留言、留言回复、好友请求、好友私信，实时轮询未读数量并支持一键已读
- 斗地主：欢乐斗地主三人围桌（上左/上右对手、自己在下方），大厅可快速匹配两名 AI 或创建房间邀请好友；完整牌型、抢地主、计分与动画
- 月牙闯关：俯视角生存闯关小游戏，滑动屏幕移动、月牙环绕自动攻击，地图上有草丛与宝箱，砍草开箱掉落月牙道具，收集提升数量与等级（颜色区分等级），关卡递进刷怪

## 数据库：独立 MySQL 服务器

本应用不在 Web 服务器上安装数据库，而是连接独立部署的 MySQL（5.7+ / MariaDB 10.2+）。

### 1. 在 MySQL 服务器上建库并授权

在 MySQL 服务器上用 root 登录后执行（把 `你的数据库密码` 换成强密码，`192.168.1.%` 换成 Web 服务器的 IP 段或固定 IP）：

```sql
CREATE DATABASE private_album CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'album_user'@'192.168.1.%' IDENTIFIED BY '你的数据库密码';
GRANT ALL PRIVILEGES ON private_album.* TO 'album_user'@'192.168.1.%';
FLUSH PRIVILEGES;
```

> 若 MySQL 与 Web 服务器在同一台机器，把 `192.168.1.%` 换成 `localhost`。
> MySQL 8 的 caching_sha2_password 若导致旧 PHP 连接失败，可改用：
> `ALTER USER 'album_user'@'192.168.1.%' IDENTIFIED WITH mysql_native_password BY '你的数据库密码';`

### 2. 开放 3306 端口

- 云服务器需在安全组放行 MySQL 服务器的 `3306` 端口（仅对 Web 服务器 IP 放行）。
- MySQL 配置 `bind-address` 需允许外部连接（默认 `127.0.0.1` 仅本机）。

### 3. 运行安装向导

访问 `https://你的域名/install.php`，填写 MySQL 服务器地址、端口、库名、账号密码与管理员账号。

- 若数据库不存在且账号有 CREATE 权限，会自动建库；
- 安装完成后**立即删除 install.php**。

安装向导会把连接信息写入 `data/config.php`（该目录已通过 `.htaccess` 禁止访问）。

### 4. 手动安装（可选）

不用向导时：复制 `config.example.php` 为 `data/config.php` 并填写信息，然后在 MySQL 中导入根目录 `schema.sql`：

```bash
mysql -h MySQL服务器地址 -u album_user -p private_album < schema.sql
```

## 宝塔部署（Web 服务器）

1. 网站 → 添加站点，PHP 选 `8.0` 或更高，需启用 `pdo_mysql` 扩展。
2. 把本目录全部文件上传到站点根目录（如 `/www/wwwroot/你的域名/`）。
3. 给这些目录写权限（755 或 775，属主为 `www`）：
   - `data/`
   - `uploads/`
   - `wenjian123/`
4. 在站点设置里打开 `session`。相册要求 `upload_max_filesize`、`post_max_size` 至少 `8M`。管理员文件管理若要传大文件，把这两项以及 `max_execution_time` 调大或设为 `0`。
5. 访问站点域名，用安装向导里设置的管理员账号登录。

### 摄像头与视频通话

- 拍照、录像、语音和视频通话都依赖浏览器的摄像头/麦克风权限，站点必须通过 **HTTPS** 访问（`localhost` 除外）。
- 照片/视频会保存到 `uploads/media/`，语音保存到 `uploads/voice/`，均通过 PHP 鉴权后读取，请确保 `uploads/` 可写。
- 视频通话使用 WebRTC，默认只配置了公共 STUN 服务器。双方处于严格 NAT 或企业网络时可能无法直连，需要在 `assets/js/call.js` 的 `ICE` 配置中加入自建 TURN 服务器。
- PHP 的 `upload_max_filesize` 与 `post_max_size` 建议调到 `25M` 以上，否则拍摄的视频可能上传失败。

## 默认账号（仅全新安装且用户表为空时）

- 账号：`admin`
- 密码：`admin123`

首次上线请立刻修改密码。

## 数据表

| 表 | 用途 |
| --- | --- |
| users | 用户（含唯一数字 ID） |
| photos | 照片 |
| messages | 留言（支持回复，可附定位、语音、照片与视频） |
| memos | 备忘录 |
| settings | 站点设置（站名、公告、注册开关、导航名称等） |
| photo_likes | 照片点赞 |
| cards | 卡密（一次性、永久时效） |
| friend_requests | 好友请求 |
| friends | 好友关系（双向存储） |
| private_messages | 好友私信（可附定位、语音、照片与视频） |
| calls | 视频通话会话（信令与状态） |
| call_ice | 视频通话 ICE 候选 |
| notifications | 消息提醒（留言、回复、好友请求、好友私信） |
| shuoshuo | 心情说说（默认好友可见） |
| diaries | 日志（默认好友可见） |
| ddz_rooms | 斗地主房间与牌局状态 |
| ddz_games | 斗地主战绩积分 |
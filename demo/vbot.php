<?php
/**
 * Created by PhpStorm.
 * User: HanSon
 * Date: 2016/12/7
 * Time: 16:33
 */

require_once __DIR__ . './../vendor/autoload.php';

use Hanson\Vbot\Foundation\Vbot;
use Hanson\Vbot\Message\Entity\Message;
use Hanson\Vbot\Message\Entity\Image;
use Hanson\Vbot\Message\Entity\Text;
use Hanson\Vbot\Message\Entity\Emoticon;
use Hanson\Vbot\Message\Entity\Location;
use Hanson\Vbot\Message\Entity\Video;
use Hanson\Vbot\Message\Entity\Voice;
use Hanson\Vbot\Message\Entity\Recall;
use Hanson\Vbot\Message\Entity\RedPacket;
use Hanson\Vbot\Message\Entity\Transfer;
use Hanson\Vbot\Message\Entity\Recommend;
use Hanson\Vbot\Message\Entity\Share;
use Hanson\Vbot\Message\Entity\Official;
use Hanson\Vbot\Message\Entity\Touch;
use Hanson\Vbot\Message\Entity\Mina;
use Hanson\Vbot\Message\Entity\RequestFriend;
use Hanson\Vbot\Message\Entity\GroupChange;
use Hanson\Vbot\Message\Entity\NewFriend;

$path = __DIR__ . '/./../tmp/';
$robot = new Vbot([
    'tmp' => $path,
    'debug' => true
]);

// 图灵自动回复
function reply($str)
{
    $result = http()->post('http://www.tuling123.com/openapi/api', [
        'key' => '1dce02aef026258eff69635a06b0ab7d',
        'info' => $str
    ], true);

    return isset($result['url']) ? $result['text'] . $result['url'] : $result['text'];
}

function xiaobing($str, $replyUsername)
{
    \Hanson\Vbot\Support\Console::debug('内容：' . $str);
    \Hanson\Vbot\Support\Console::debug('对象：' . $replyUsername);
    Text::send($replyUsername, $str);
}

// 设置管理员
function isAdmin($message)
{
    $adminAlias = 'hanson1994';

    if (in_array($message->fromType, ['Contact', 'Group'])) {
        if ($message->fromType === 'Contact') {
            return $message->from['Alias'] === $adminAlias;
        } else {
            return isset($message->sender['Alias']) && $message->sender['Alias'] === $adminAlias;
        }
    }

    return false;
}

$groupMap = [
    [
        'nickname' => 'vbot 测试群',
        'id' => 1
    ]
];

$robot->server->setOnceHandler(function () use ($groupMap) {

    group()->each(function ($group, $key) use ($groupMap) {
        foreach ($groupMap as $map) {
            if ($group['NickName'] === $map['nickname']) {
                $group['id'] = $map['id'];
                $groupMap[$key] = $map['id'];
                group()->setMap($key, $map['id']);
            }
        }
        return $group;
    });
});

$replyMap = [];
$robot->server->setMessageHandler(function ($message) use ($path, &$replyMap) {
    /** @var $message Message */
    $replyUsername = official()->getUsernameByNickname('小冰');

    \Hanson\Vbot\Support\Console::debug('msgId: ' . $message->raw['MsgId']);

    // 位置信息 返回位置文字
    if ($message instanceof Location) {
        /** @var $message Location */
        Text::send('地图链接：' . $message->from['UserName'], $message->url);
        return '位置：' . $message;
    }


    if ($message->from['UserName'] === $replyUsername) {
        if ($message instanceof Text) {
            $username = last($replyMap);
            Text::send($username, $message->content);
        }
        unset($replyMap[count($replyMap) - 1]);
        return false;
    }

    // 文字信息
    if ($message instanceof Text) {
        /** @var $message Text */

        if ($message->from['NickName'] === '华广stackoverflow' && preg_match('/@(.+)\s加人(.+)/', $message->content, $match)){
            $nickname = $match[1];
            $members = group()->getMembersByNickname($message->from['UserName'], $nickname);
            if ($members) {
                $member = current($members);
                echo $match[2].PHP_EOL;
                contact()->add($member['UserName'], $match[2]);
            }
        }

        if (str_contains($message->content, 'vbot') && !$message->isAt) {
            return "你好，我叫vbot，我爸是HanSon\n我的项目地址是 https://github.com/HanSon/vbot \n欢迎来给我star！";
        }

        if ($message->content === 'emoji') {
            return ;
        }

        // 联系人自动回复
        if ($message->fromType === 'Contact') {
            if ($message->content === '拉我') {
                $username = group()->getUsernameById(1);

                group()->addMember($username, $message->from['UserName']);
                return false;
            }


//            $replyMap[] = $message->from['UserName'];
//            xiaobing($message->content, $replyUsername);
//            return false;

            return reply($message->content);
            // 群组@我回复
        } elseif ($message->fromType === 'Group') {

            if (str_contains($message->content, '设置群名称')) {
                if (isAdmin($message)) {
                    group()->setGroupName($message->from['UserName'], str_replace('设置群名称', '', $message->content));
                } else {
                    return '你没有此权限';
                }
            }

            if (str_contains($message->content, '搜人')) {
                $nickname = str_replace('搜人', '', $message->content);
                $members = group()->getMembersByNickname($message->from['UserName'], $nickname, true);
                $result = '搜索结果 数量：' . count($members) . "\n";
                foreach ($members as $member) {
                    $result .= $member['NickName'] . ' ' . $member['UserName'] . "\n";
                }
                return $result;
            }

            if (str_contains($message->content, '踢人')) {
                if (isAdmin($message)) {
                    $username = str_replace('踢人', '', $message->content);
                    group()->deleteMember($message->from['UserName'], $username);
                } else {
                    return '你没有此权限';
                }
            }

            if (str_contains($message->content, '踢我') && $message->isAt) {
                Text::send($message->from['UserName'], '拜拜 ' . $message->sender['NickName']);
                group()->deleteMember($message->from['UserName'], $message->sender['UserName']);
            }

            if (substr($message->content, 0, 1) === '@' && preg_match('/@(.+)\s自作孽不可活/', $message->content, $match)) {
                if (isAdmin($message)) {
                    $nickname = $match[1];
                    $members = group()->getMembersByNickname($message->from['UserName'], $nickname);
                    if ($members) {
                        $member = current($members);
                        Text::send($message->from['UserName'], '拜拜 ' . $member['NickName'] . ' ，君让臣死，臣不得不死');
                        group()->deleteMember($message->from['UserName'], $member['UserName']);
                    }
                } else {
                    return '你没有此权限';
                }
            }

            if ($message->isAt) {
//                $replyMap[] = $message->from['UserName'];
//                xiaobing($message->content, $replyUsername);
//                return false;
                return reply($message->content);
            }
        }
    }

    // 图片信息 返回接收到的图片
    if ($message instanceof Image) {
//        return $message;
    }

    // 视频信息 返回接收到的视频
    if ($message instanceof Video) {
//        return $message;
    }

    // 表情信息 返回接收到的表情
    if ($message instanceof Emoticon && random_int(0, 1) && random_int(0, 1)) {
        Emoticon::sendRandom($message->from['UserName']);
    }

    // 语音消息
    if ($message instanceof Voice) {
        /** @var $message Voice */
//        return '收到一条语音并下载在' . $message::getPath($message::$folder) . "/{$message->raw['MsgId']}.mp3";
    }

    // 撤回信息
    if ($message instanceof Recall && $message->raw['FromUserName'] !== myself()->username) {
        /** @var $message Recall */
        if ($message->origin instanceof Image) {
            Text::send($message->raw['FromUserName'], "{$message->nickname} 撤回了一张照片");
            Image::sendByMsgId($message->raw['FromUserName'], $message->origin->raw['MsgId']);
        } elseif ($message->origin instanceof Emoticon) {
            Text::send($message->raw['FromUserName'], "{$message->nickname} 撤回了一个表情");
            Emoticon::sendByMsgId($message->raw['FromUserName'], $message->origin->raw['MsgId']);
        } elseif ($message->origin instanceof Video) {
            Text::send($message->raw['FromUserName'], "{$message->nickname} 撤回了一个视频");
            Video::sendByMsgId($message->raw['FromUserName'], $message->origin->raw['MsgId']);
        } elseif ($message->origin instanceof Voice) {
            Text::send($message->raw['FromUserName'], "{$message->nickname} 撤回了一条语音");
        } else {
            Text::send($message->raw['FromUserName'], "{$message->nickname} 撤回了一条信息 \"{$message->origin->message}\"");
        }
    }

    // 红包信息
    if ($message instanceof RedPacket) {
        // do something to notify if you want ...
        return $message->content . ' 来自 ' . $message->from['NickName'];
    }

    // 转账信息
    if ($message instanceof Transfer) {
        /** @var $message Transfer */
        return $message->content . ' 收到金额 ' . $message->fee . ' 转账说明： ' . $message->memo ?: '空';
    }

    // 推荐名片信息
    if ($message instanceof Recommend) {
        /** @var $message Recommend */
        if ($message->isOfficial) {
            return $message->from['NickName'] . ' 向你推荐了公众号 ' . $message->province . $message->city .
            " {$message->info['NickName']} 公众号信息： {$message->description}";
        } else {
            return $message->from['NickName'] . ' 向你推荐了 ' . $message->province . $message->city .
            " {$message->info['NickName']} 头像链接： {$message->bigAvatar}";
        }
    }

    // 请求添加信息
    if ($message instanceof RequestFriend) {
        /** @var $message RequestFriend */
        $groupUsername = group()->getGroupsByNickname('芬芬', true)->first()['UserName'];

        Text::send($groupUsername, "{$message->info['NickName']} 请求添加好友 \"{$message->info['Content']}\"");

        if ($message->info['Content'] === '上山打老虎') {
            Text::send($groupUsername, '暗号正确');
            $message->verifyUser($message::VIA);
        } else {
            Text::send($groupUsername, '暗号错误');
        }
    }

    //分享信息
    if ($message instanceof Share) {
        /** @var $message Share */
        $reply = "收到分享\n标题：{$message->title}\n描述：{$message->description}\n链接：{$message->url}";
        if ($message->app) {
            $reply .= "\n来源APP：{$message->app}";
        }
        return $reply;
    }

    // 分享小程序信息
    if ($message instanceof Mina) {
        /** @var $message Mina */
        $reply = "收到小程序\n小程序名词：{$message->title}\n链接：{$message->url}";
        return $reply;
    }

    // 公众号推送信息
    if ($message instanceof Official) {
        /** @var $message Official */
        $reply = "收到公众号推送\n标题：{$message->title}\n描述：{$message->description}\n链接：{$message->url}\n来源公众号名称：{$message->app}";
        return $reply;
    }

    // 手机点击聊天事件
    if ($message instanceof Touch) {
//        Text::send($message->raw['ToUserName'], "我点击了此聊天");
    }

    // 新增好友
    if ($message instanceof NewFriend) {
        \Hanson\Vbot\Support\Console::debug('新加好友：' . $message->from['NickName']);
        Text::send($message->from['UserName'], "客官，等你很久了！感谢跟 vbot 交朋友，如果可以帮我点个star，谢谢了！https://github.com/HanSon/vbot");
        group()->addMember(group()->getUsernameById(1), $message->from['UserName']);
        return '现在拉你进去vbot的测试群，进去后为了避免轰炸记得设置免骚扰哦！如果被不小心踢出群，跟我说声“拉我”我就会拉你进群的了。';
    }

    // 群组变动
    if ($message instanceof GroupChange) {
        /** @var $message GroupChange */
        if ($message->action === 'ADD') {
            \Hanson\Vbot\Support\Console::debug('新人进群');
            if ($message->from['NickName'] === '华广stackoverflow') {
                return "欢迎 {$message->nickname} 同学加入华广技术交流群！我是这里的群管家vbot，进群先给我点个star吧， https://github.com/HanSon/vbot";
            } else {
                return '欢迎新人 ' . $message->nickname;
            }
        } elseif ($message->action === 'REMOVE') {
            \Hanson\Vbot\Support\Console::debug('群主踢人了');
            return $message->content;
        } elseif ($message->action === 'RENAME') {
//            \Hanson\Vbot\Support\Console::log($message->from['NickName'] . ' 改名为 ' . $message->rename);
            if (group()->getUsernameById(1) == $message->from['UserName'] && $message->rename !== 'vbot 测试群') {
                group()->setGroupName($message->from['UserName'], 'vbot 测试群');
                return '行不改名,坐不改姓！';
            }
        } elseif ($message->action === 'BE_REMOVE') {
            \Hanson\Vbot\Support\Console::debug('你被踢出了群 ' . $message->group['NickName']);
        } elseif ($message->action === 'INVITE') {
            \Hanson\Vbot\Support\Console::debug('你被邀请进群 ' . $message->from['NickName']);
        }
    }

    return false;

});

$robot->server->setExitHandler(function () {
    \Hanson\Vbot\Support\Console::log('其他设备登录');
});

$robot->server->setExceptionHandler(function () {
    \Hanson\Vbot\Support\Console::log('异常退出');
});

$robot->server->run();

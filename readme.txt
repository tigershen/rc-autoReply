=== postfix 自動回信機制 ===

--- postfix 部分 ---
利用 alias 和 transport 的機制, 
在 alias 加上一筆 xxx@ooo@autoreply.tld 的紀錄
配合 transport 的設定, 去觸發執行外部程式.

# main.cf
transport_maps = hash:$config_directory/hash-transport_maps.cf

# hash-transport_maps.cf
autoreply.tld   autoreply:

# master.cf
autoreply unix  -       n       n       -       -       pipe
        flags= user=nobody argv=/usr/local/etc/postfix/mailScript/autoReply.php $sender $mailbox


--- roundcube 部分 ---
自行寫一個plugin, 在webmail介面可以設定auto reply
在 mariadb 新增一個 autoReply 的 table, 存放 subject 和 body
開啟/關閉 autoreply 時, 會去修改 postfix.alias

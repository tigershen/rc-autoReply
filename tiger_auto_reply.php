<?php /* plugin for roundcube, 設定auto reply的訊息*/

class tiger_auto_reply extends rcube_plugin
{
    public $task    = 'settings';
    public $noframe = true;
    public $noajax  = true;

    function init(){
        $this->app = rcmail::get_instance();
        $this->email = $this->app->user->get_username();
        if ($this->app->task == 'settings') {
            $this->add_texts('localization/'); //roundcube 多語言功能
            $this->add_hook('settings_actions', array($this, 'settings_actions')); //roundcube settings
            $this->register_action('plugin.tiger_auto_reply', array($this, 'tiger_auto_reply_init'));
            $this->register_action('plugin.tiger_auto_reply-save', array($this, 'tiger_auto_reply_save'));
        }
    }

    function settings_actions($args) {
        $args['actions'][] = array(
            'action' => 'plugin.tiger_auto_reply',
            'class'  => 'filter', // tab icon
            'label'  => $this->gettext('settingsTabLabel'), // tab text
            'title'  => $this->gettext('pageTitle'), // tab title
            'domain' => 'tiger_auto_reply',
        );
        return $args;
    }

    function tiger_auto_reply_init(){
        $this->register_handler('plugin.body', array($this, 'tiger_auto_reply_form'));
        $this->app->output->set_pagetitle($this->gettext('pageTitle'));
        $this->app->output->send('plugin');
    }

    function tiger_auto_reply_save(){
        $this->register_handler('plugin.body', array($this, 'tiger_auto_reply_form'));
        $this->app->output->set_pagetitle($this->gettext('pageTitle'));

        //儲存auto reply
        if( strlen(trim($_POST['_subject']))==0 or strlen(trim($_POST['_body']))==0 ){
            $this->app->output->command('display_message', 'subject and body can not be empty', 'error');
        }else{ //save to db
            $this->setAutoReply($_POST['_subject'], $_POST['_body']);
            $this->app->output->command('display_message', 'save to db ', 'info');
        }

        //開關 alias
        $this->setAlias(isset($_POST['_active']) ? true : false);

        $this->app->overwrite_action('plugin.tiger_auto_reply');
        $this->app->output->send('plugin');
    }

    function tiger_auto_reply_form(){
        // 產生設定介面的form
        $row = $this->getAutoReply();
        $htmlOut = html::div(array('id' => 'prefs-title', 'class' => 'boxtitle'), $this->gettext('pageTitle'));

        $htmlOut .= '<div class="boxcontent">';
        $table = new html_table(array('cols' => 2));
        $table->add('title', sprintf('<label for="factive">%s</label>', $this->gettext('active')));
        $table->add(null, 
            sprintf('<input id="factive" name="_active" type="checkbox" %s>',
            (isset($row['goto']) AND strpos($row['goto'], '@autoreply.tld')) ? 'checked' : ''
        ));

        $table->add('title', sprintf('<label for="fsubj">%s</label>', $this->gettext('subject')));
        $table->add(null, 
            sprintf('<input id="fsubj" size="60" name="_subject" type="text" value="%s" autocomplete="off">',
            isset($row['subject']) ? $row['subject'] : 'auto reply from '.$this->email));

        $defaultBody = "I am not in office right now, reply you later.";
        $defaultBody.= "\n\n我不在辦公室, 晚點回你mail.";
        $table->add('title', sprintf('<label for="fbody">%s</label>', $this->gettext('body')));
        $table->add(null, 
            sprintf('<textarea id="fbody" name="_body" cols=60 rows=8>%s</textarea>',
            isset($row['body']) ? $row['body'] : $defaultBody));
        $htmlOut .= $table->show();

        $submit_button = $this->app->output->button(array(
            'command' => 'plugin.tiger_auto_reply-save',
            'type'    => 'input',
            'class'   => 'button mainaction',
            'label'   => 'save',
        ));
        $form_buttons = html::p(array('class' => 'formbuttons'), $submit_button);
        $htmlOut .= $form_buttons;

        $htmlOut .= sprintf('<input size=80 disabled value="%s">', $row['goto']);
        //$htmlOut .= json_encode($_POST);
        $htmlOut .= '</div>';

        $this->app->output->add_gui_object('tiger_auto_reply', 'tiger_auto_reply-form');
        $this->include_script('tiger_auto_reply.js');

        return $this->app->output->form_tag(array(
            'id'     => 'tiger_auto_reply-form',
            'name'   => 'tiger_auto_reply-form',
            'method' => 'post',
            'action' => './?_task=settings&_action=plugin.tiger_auto_reply-save',
        ), $htmlOut);
    }

    function getAutoReply(){
        // 取得 postfix.alias 和 postfix.autoReply
        $dbh = $this->get_dbh();
        $sql = sprintf('SELECT a.goto, r.subject, r.body FROM postfix.alias a '.
            'LEFT JOIN postfix.autoReply r ON (a.address=r.email) '.
            'WHERE a.address="%s"', $this->email);
        $sql_result = $dbh->query($sql);
        return $dbh->fetch_assoc($sql_result);
    }

    function setAutoReply($subject='', $body=''){
        // 儲存 postfix.autoReply 的資料
        $dbh = $this->get_dbh();
        $subject = $dbh->escape($subject);
        $body = $dbh->escape($body);
        $sql = sprintf('INSERT INTO postfix.autoReply (email,subject,body) VALUES ("%s","%s","%s") '.
        'ON DUPLICATE KEY UPDATE email="%s", subject="%s", body="%s"'
        , $this->email, $subject, $body, $this->email, $subject, $body);
        $sql_result = $dbh->query($sql);
    }

    function setAlias($set){
        // 變更 postfix.alias 
        $dbh = $this->get_dbh();
        $row = $this->getAutoReply();
        $origStatus = (isset($row['goto']) AND strpos($row['goto'], '@autoreply.tld')) ? true : false;

        if($set === $origStatus) return; //開關狀態沒有改變
        $sql = '';
        if($set){//在postfix.alias加入@autoreply.tld
            if(isset($row['goto'])){
                $sql = sprintf('UPDATE postfix.alias SET goto="%s" WHERE address="%s"',
                    $row['goto'].' '.$this->email.'@autoreply.tld', $this->email);
            }else{
                $sql = sprintf('INSERT INTO postfix.alias (address,goto,type) VALUES ("%s","%s", "self")',
                    $this->email, $this->email.' '.$this->email.'@autoreply.tld');
            }
        }else{//在postfix.alias拿掉@autoreply.tld
            //先拿掉autoreply.tld, 若只剩下原email則直接刪除alias
            $output = preg_replace('/\s'.$this->email.'@autoreply.tld/', '', $row['goto']);
            if( $output == $this->email ){
                $sql = sprintf('DELETE FROM postfix.alias WHERE address="%s"', $this->email);
            } else {
                $sql = sprintf('UPDATE postfix.alias SET goto="%s" WHERE address="%s"', $output, $this->email);
            }
        }
        $sql_result = $dbh->query($sql);
    }

    function get_dbh() {
        if (!$this->db) {
            if ($dsn = $this->app->config->get('tiger_auto_reply_dsn')) {
                $this->db = rcube_db::factory($dsn);
                $this->db->set_debug((bool)$this->app->config->get('sql_debug'));
                $this->db->db_connect('w'); // connect in read mode
            }
            else {
                $this->db = $this->app->get_dbh();
            }
        }

        return $this->db;
    }


}
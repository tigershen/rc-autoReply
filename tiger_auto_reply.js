// plugin for roundcube
window.rcmail && rcmail.addEventListener('init', function(evt) {

  //需註冊後 按鈕的disabled才會消失...
  rcmail.register_command('plugin.tiger_auto_reply-save', function(){
    var input_active = rcube_find_object('_active')
    var input_subject = rcube_find_object('_subject')
    var input_body = rcube_find_object('_body')

    rcmail.gui_objects.tiger_auto_reply.submit()
  }, true)

})
<?php
shell_exec("/usr/local/bin/php  /etc/rc.filter_configure");
echo json_encode(array('status'=>200,'msg'=>'success'));
exit();
?>

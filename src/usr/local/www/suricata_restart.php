<?php
shell_exec("/usr/local/etc/rc.d/suricata.sh restart");
echo json_encode(array('status'=>200,'msg'=>'success'));
exit();
?>

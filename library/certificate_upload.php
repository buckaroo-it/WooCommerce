<?php
$filename = 'BuckarooPrivateKey.pem';//basename($_FILES['buckaroo_certificate_file']['name']);
if (move_uploaded_file($_FILES['buckaroo_certificate_file']['tmp_name'], dirname(__FILE__) .'/certificate/' . $filename)) {
    $display_name = $filename." (".date('Y-m-d H:i:s').")";
    $data = array('filename' => $filename, 'display_name' => $display_name, 'error' => '');
} else {
    $data = array('error' => 'Failed to save. Please make "includes/modules/paymet/buckaroo3/certificate/" directory writeable!');
}

header('Content-type: text/html');
echo json_encode($data);
?>
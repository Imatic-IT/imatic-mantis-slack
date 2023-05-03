<?php

if ($_POST) {


    if (isset($_POST['add_channel']) && !empty($_POST['add_channel'])) {
        plugin_get()->addChanel($_POST);

    }

    if (isset($_POST['delete_channel']) && !empty($_POST['delete_channel'])) {
        plugin_get()->deleteChannel($_POST['delete_channel']);
    }

    if (isset($_POST['assign_channel']) && !empty($_POST['assign_channel'])) {;
        plugin_get()->assignChannel($_POST);
    }


//    pre_r($_POST);
    header('Location: ' . $_SERVER['HTTP_REFERER']);

}


// Delete channel
if ($_GET) {

    if (isset($_GET['delete_channel_id']) && !empty($_GET['delete_channel_id'])) {
        plugin_get()->deleteChannel($_GET['delete_channel_id']);
    }

    if (isset($_GET['delete_assign_channel_id']) && !empty($_GET['delete_assign_channel_id'])) {
        plugin_get()->deleteAssignChannel($_GET['delete_assign_channel_id']);
    }


//    pre_r($_GET);
    header('Location: ' . $_SERVER['HTTP_REFERER']);

}


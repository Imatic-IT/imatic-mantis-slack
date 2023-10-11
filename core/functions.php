<?php

require_api('database_api.php');


function imaticAssignChannel($data)
{

    $user_id = user_get_id_by_name($data['user_name']);

    if (plugin_get()->userHasAssignedChannel($user_id)) return;

    $channelName = plugin_get()->getChannelName($_POST['channel_id']);
    $channelUrl = plugin_get()->getChannelUrl($_POST['channel_id']);


    if (!$user_id) return;
    if (!$channelName) return;


    $db = db_get_table('plugin_imatic_slack_assigned_channels');

    db_param_push();
    $t_query = 'INSERT INTO ' . $db . "
            ( username, user_id, channel_id, channel_name, channel_webhook_url)
            VALUES
            ( " . db_param() . ', ' . db_param() . ', ' . db_param() . ', ' . db_param() . ', ' . db_param() . ')';

    db_query($t_query, array($data['user_name'], $user_id, $data['channel_id'], $channelName, $channelUrl));

    return db_affected_rows($db);
}


function imaticAddChannel($channel)
{

    if (empty($channel['channel_name'] && $channel['channel_webhook_url'])) return;

    $db = db_get_table('plugin_imatic_slack_channels');


    db_param_push();
    $t_query = 'INSERT INTO ' . $db . "
            ( channel_name, channel_webhook_url)
            VALUES
            ( " . db_param() . ', ' . db_param() . ')';

    db_query($t_query, array($channel['channel_name'], $channel['channel_webhook_url']));

    return db_affected_rows($db);

}


function imaticGetAllChannels()
{
    $t_query = 'SELECT * FROM ' . db_get_table('plugin_imatic_slack_channels');

    $t_result = db_query($t_query);

    $channels = [];

    while ($row = db_fetch_array($t_result)) {
        $channels[] = $row;
    }

    return $channels;
}

function imaticGetAllAssignedChannels()
{
    $t_query = 'SELECT * FROM ' . db_get_table('plugin_imatic_slack_assigned_channels');

    $t_result = db_query(
        $t_query
    );

    $assignedChannels = [];

    while ($row = db_fetch_array($t_result)) {
        $assignedChannels[] = $row;
    }

    return $assignedChannels;
}

function imaticGetAllUsersWithAssignedChannel()
{

    $t_query = 'SELECT * FROM ' . db_get_table('plugin_imatic_slack_assigned_channels');

    $t_result = db_query($t_query);

    $assigned = [];

    while ($row = db_fetch_array($t_result)) {
        $assigned[] = $row;
    }

    if (empty($assigned)) return [];

    $users = [];

    foreach ($assigned as $assign) {

        $users[] = (int)$assign['user_id'];
    }
    return $users;
}


function imaticDeleteChanel($id)
{

    $t_query = 'DELETE  FROM ' . db_get_table('plugin_imatic_slack_channels') . ' WHERE id = ' . $id;

    $t_result = db_query(
        $t_query
    );

    $rows = db_affected_rows($t_result);

    return $rows;
}
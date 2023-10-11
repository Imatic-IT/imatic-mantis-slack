<?php


if (isset($_GET['id'])) {
    $bug_id = $_GET['id'];
    $issue = bug_get_row($bug_id);

    $bug = bug_get($bug_id);
    $handler_id = $bug->handler_id;
    $project = project_get_name($bug->project_id);
    $url = string_get_bug_view_url_with_fqdn($bug_id);

    $summary = plugin_get()->format_summary($bug);
    $handler_name = plugin_get()->get_user_name($handler_id);

    $msg = sprintf(plugin_lang_get('bug_reminder'),
        $project, $handler_name, $url, $summary
    );

    $assigned = imaticGetAllAssignedChannels();
    $webhook = plugin_get()->getChannelUrlByReporterId($handler_id);

    $response =   plugin_get()->notify($msg, $webhook, plugin_get()->get_channel($project), plugin_get()->get_attachment($bug));

    if ($msg =  $response == 'ok' ? 'Upozornenie na slack bolo úspešne odoslané.' : 'Upozornenie na slack nebolo odoslané.')
    if ($status =  $response == 'ok' ? 'success' : 'error')

    $response = array(
        'status' => $status,
        'message' => $msg
    );
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
} else {

    $response = array(
        'status' => 'error',
        'message' => 'Chyba: nebol poskytnutý ID problému.'
    );
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}


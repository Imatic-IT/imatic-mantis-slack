<?php
/**
 * Slack Integration
 * Copyright (C) Karim Ratib (karim.ratib@gmail.com)
 *
 * Slack Integration is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License 2
 * as published by the Free Software Foundation.
 *
 * Slack Integration is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Slack Integration; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA
 * or see http://www.gnu.org/licenses/.
 */

class ImaticSlackPlugin extends MantisPlugin
{
    var $skip = false;

    function register()
    {
        $this->name = plugin_lang_get('title');
        $this->description = plugin_lang_get('description');
        $this->page = 'config_page';
        $this->version = '2.1.3';
        $this->requires = array(
            'MantisCore' => '2.0.0',
        );
        $this->author = 'Karim Ratib, Imatic Software s.r.o.';
        $this->contact = 'karim.ratib@gmail.com, info@imatic.cz';
        $this->url = 'https://karimratib.me, https://www.imatic.cz/';
    }

    function install()
    {
        if (version_compare(PHP_VERSION, '5.3.0', '<')) {
            plugin_error('ERROR_PHP_VERSION');
            return false;
        }
        if (!extension_loaded('curl')) {
            plugin_error('ERROR_NO_CURL');
            return false;
        }
        return true;
    }

    function config()
    {
        return array(
            'url_webhooks' => array(),
            'url_webhook' => '',
            'bot_name' => 'mantis',
            'bot_icon' => '',
            'skip_private' => true,
            'skip_bulk' => true,
            'link_names' => false,
            'channels' => array(),
            'default_channel' => '#general',
            'usernames' => array(),
            'columns' => array(
                'status',
                'handler_id',
                'target_version',
                'priority',
                'severity',
            ),
            'notification_bug_report' => true,
            'notification_bug_update' => true,
            'notification_bug_deleted' => true,
            'notification_bugnote_add' => true,
            'notification_bugnote_edit' => true,
            'notification_bugnote_deleted' => true,
            'imatic_channels' => array(),
            'imatic_assigned_channels' => array(),
            'imatic_users_with_assigned_channels' => $this->getAllUsersWithAssignedChannel(),
            'imatic_text_after_recipient_if_has_channel' => '( &#128172;  Slack )',
            'imatic_button_reminder_settings' => array(
                'text' => plugin_lang_get('imatic_bug_reminder_page_button'),
                'iconSrc' => plugin_file("slack-icon.png"),
                'disable_if_user_not_have_assign_channel' => false
            )

        );
    }

    function hooks()
    {
        return array(
            'EVENT_REPORT_BUG' => 'bug_report',
            'EVENT_UPDATE_BUG' => 'bug_update',
            'EVENT_BUG_DELETED' => 'bug_deleted',
            'EVENT_BUG_ACTION' => 'bug_action',
            'EVENT_BUGNOTE_ADD' => 'bugnote_add_edit',
            'EVENT_BUGNOTE_EDIT' => 'bugnote_add_edit',
            'EVENT_BUGNOTE_DELETED' => 'bugnote_deleted',
            'EVENT_BUGNOTE_ADD_FORM' => 'bugnote_add_form',
            'EVENT_VIEW_BUG_DETAILS' => 'bug_view_details',
            'EVENT_LAYOUT_BODY_END' => 'layout_body_end_hook',
            'EVENT_CORE_READY' => 'event_core_ready',
        );
    }


    function skip_private($bug_or_note)
    {
        return (
            $bug_or_note->view_state == VS_PRIVATE &&
            plugin_config_get('skip_private')
        );
    }

    function skip_event($event)
    {
        $configs = array(
            'EVENT_REPORT_BUG' => 'notification_bug_report',
            'EVENT_UPDATE_BUG' => 'notification_bug_update',
            'EVENT_BUG_DELETED' => 'notification_bug_deleted',
            'EVENT_BUGNOTE_ADD' => 'notification_bugnote_add',
            'EVENT_BUGNOTE_EDIT' => 'notification_bugnote_edit',
            'EVENT_BUGNOTE_DELETED' => 'notification_bugnote_deleted',
        );
        if (!array_key_exists($event, $configs)) return true;
        return !plugin_config_get($configs[$event]);
    }

    function bugnote_add_form($event, $bug_id)
    {
        if ($_SERVER['PHP_SELF'] !== '/bug_update_page.php') return;

        echo '<tr>';
        echo '<th class="category">' . plugin_lang_get('skip') . '</th>';
        echo '<td colspan="5">';
        echo '<label>';
        echo '<input ', helper_get_tab_index(), ' name="slack_skip" class="ace" type="checkbox" />';
        echo '<span class="lbl"></span>';
        echo '</label>';
        echo '</td></tr>';
    }

    function bug_report_update($event, $bug, $bug_id)
    {
        $this->skip = $this->skip ||
            gpc_get_bool('slack_skip') ||
            $this->skip_private($bug) ||
            $this->skip_event($event);

        $project = project_get_name($bug->project_id);
        $url = string_get_bug_view_url_with_fqdn($bug_id);
        $summary = $this->format_summary($bug);
        $reporter = $this->get_user_name(auth_get_current_user_id());
        $msg = sprintf(plugin_lang_get($event === 'EVENT_REPORT_BUG' ? 'bug_created' : 'bug_updated'),
            $project, $reporter, $url, $summary
        );
        $this->notify($msg, $this->get_webhook($project), $this->get_channel($project), $this->get_attachment($bug));
    }

    function bug_report($event, $bug, $bug_id)
    {
        $this->bug_report_update($event, $bug, $bug_id);
    }

    function bug_update($event, $bug_existing, $bug_updated)
    {
        $this->bug_report_update($event, $bug_updated, $bug_updated->id);
    }

    function bug_action($event, $action, $bug_id)
    {
        $this->skip = $this->skip ||
            gpc_get_bool('slack_skip') ||
            plugin_config_get('skip_bulk');

        if ($action !== 'DELETE') {
            $bug = bug_get($bug_id);
            $this->bug_report_update('EVENT_UPDATE_BUG', $bug, $bug_id);
        }
    }

    function bug_deleted($event, $bug_id)
    {
        $bug = bug_get($bug_id);

        $this->skip = $this->skip ||
            gpc_get_bool('slack_skip') ||
            $this->skip_private($bug) ||
            $this->skip_event($event);

        $project = project_get_name($bug->project_id);
        $reporter = $this->get_user_name(auth_get_current_user_id());
        $summary = $this->format_summary($bug);
        $msg = sprintf(plugin_lang_get('bug_deleted'), $project, $reporter, $summary);
        $this->notify($msg, $this->get_webhook($project), $this->get_channel($project));
    }

    function bugnote_add_edit($event, $bug_id, $bugnote_id, $files = null)
    {
        $bug = bug_get($bug_id);
        $bugnote = bugnote_get($bugnote_id);

        $this->skip = $this->skip ||
            gpc_get_bool('slack_skip') ||
            $this->skip_private($bug) ||
            $this->skip_private($bugnote) ||
            $this->skip_event($event);

        $url = string_get_bugnote_view_url_with_fqdn($bug_id, $bugnote_id);
        $project = project_get_name($bug->project_id);
        $summary = $this->format_summary($bug);
        $reporter = $this->get_user_name(auth_get_current_user_id());
        $note = bugnote_get_text($bugnote_id);
        $msg = sprintf(plugin_lang_get($event === 'EVENT_BUGNOTE_ADD' ? 'bugnote_created' : 'bugnote_updated'),
            $project, $reporter, $url, $summary
        );
        $this->notify($msg, $this->get_webhook($project), $this->get_channel($project), $this->get_text_attachment($this->bbcode_to_slack($note)));
    }

    function get_text_attachment($text)
    {
        $attachment = array('color' => '#3AA3E3', 'mrkdwn_in' => array('pretext', 'text', 'fields'));
        $attachment['fallback'] = $text . "\n";
        $attachment['text'] = $text;
        return $attachment;
    }

    function bugnote_deleted($event, $bug_id, $bugnote_id)
    {
        $bug = bug_get($bug_id);
        $bugnote = bugnote_get($bugnote_id);

        $this->skip = $this->skip ||
            gpc_get_bool('slack_skip') ||
            $this->skip_private($bug) ||
            $this->skip_private($bugnote) ||
            $this->skip_event($event);

        $project = project_get_name($bug->project_id);
        $url = string_get_bug_view_url_with_fqdn($bug_id);
        $summary = $this->format_summary($bug);
        $reporter = $this->get_user_name(auth_get_current_user_id());
        $msg = sprintf(plugin_lang_get('bugnote_deleted'), $project, $reporter, $url, $summary);
        $this->notify($msg, $this->get_webhook($project), $this->get_channel($project));
    }

    function format_summary($bug)
    {
        return bug_format_id($bug->id) . ': ' . $this->format_text($bug->summary);
    }

    function format_text($text)
    {
        return strip_tags(
            str_replace(
                array('&', '<', '>'),
                array('&amp;', '&lt;', '&gt;'),
                $this->bbcode_to_slack($text)
            )
        );
    }

    function get_attachment($bug)
    {
        $attachment = array('fallback' => '', 'color' => '#3AA3E3', 'mrkdwn_in' => array('pretext', 'text', 'fields'));
        $t_columns = (array)plugin_config_get('columns');
        foreach ($t_columns as $t_column) {
            $title = column_get_title($t_column);
            $value = $this->format_value($bug, $t_column);

            if ($title && $value) {
                $attachment['fallback'] .= $title . ': ' . $value . "\n";
                $attachment['fields'][] = array(
                    'title' => $title,
                    'value' => $value,
                    'short' => $this->is_field_short($t_column),
                );
            }
        }
        return $attachment;
    }

    function is_field_short($column)
    {
        $id = custom_field_get_id_from_name(str_replace('custom_', '', $column));
        if ($id) {
            $field = custom_field_get_definition($id);
            return $field['type'] != CUSTOM_FIELD_TYPE_TEXTAREA;
        }

        return !column_is_extended($column);
    }

    function format_value($bug, $field_name)
    {
        $self = $this;
        $values = array(
            'id' => function ($bug) {
                return sprintf('<%s|%s>', string_get_bug_view_url_with_fqdn($bug->id), $bug->id);
            },
            'project_id' => function ($bug) {
                return project_get_name($bug->project_id);
            },
            'reporter_id' => function ($bug) {
                return $this->get_user_name($bug->reporter_id);
            },
            'handler_id' => function ($bug) {
                return empty($bug->handler_id) ? plugin_lang_get('no_user') : $this->get_user_name($bug->handler_id);
            },
            'duplicate_id' => function ($bug) {
                return sprintf('<%s|%s>', string_get_bug_view_url_with_fqdn($bug->duplicate_id), $bug->duplicate_id);
            },
            'priority' => function ($bug) {
                return get_enum_element('priority', $bug->priority);
            },
            'severity' => function ($bug) {
                return get_enum_element('severity', $bug->severity);
            },
            'reproducibility' => function ($bug) {
                return get_enum_element('reproducibility', $bug->reproducibility);
            },
            'status' => function ($bug) {
                return get_enum_element('status', $bug->status);
            },
            'resolution' => function ($bug) {
                return get_enum_element('resolution', $bug->resolution);
            },
            'projection' => function ($bug) {
                return get_enum_element('projection', $bug->projection);
            },
            'category_id' => function ($bug) {
                return category_full_name($bug->category_id, false);
            },
            'eta' => function ($bug) {
                return get_enum_element('eta', $bug->eta);
            },
            'view_state' => function ($bug) {
                return $bug->view_state == VS_PRIVATE ? lang_get('private') : lang_get('public');
            },
            'sponsorship_total' => function ($bug) {
                return sponsorship_format_amount($bug->sponsorship_total);
            },
            'os' => function ($bug) {
                return $bug->os;
            },
            'os_build' => function ($bug) {
                return $bug->os_build;
            },
            'platform' => function ($bug) {
                return $bug->platform;
            },
            'version' => function ($bug) {
                return $bug->version;
            },
            'fixed_in_version' => function ($bug) {
                return $bug->fixed_in_version;
            },
            'target_version' => function ($bug) {
                return $bug->target_version;
            },
            'build' => function ($bug) {
                return $bug->build;
            },
            'summary' => function ($bug) use ($self) {
                return $self->format_summary($bug);
            },
            'last_updated' => function ($bug) {
                return date(config_get('short_date_format'), $bug->last_updated);
            },
            'date_submitted' => function ($bug) {
                return date(config_get('short_date_format'), $bug->date_submitted);
            },
            'due_date' => function ($bug) {
                return date(config_get('short_date_format'), $bug->due_date);
            },
            'description' => function ($bug) use ($self) {
                return $self->format_text($bug->description);
            },
            'steps_to_reproduce' => function ($bug) use ($self) {
                return $self->format_text($bug->steps_to_reproduce);
            },
            'additional_information' => function ($bug) use ($self) {
                return $self->format_text($bug->additional_information);
            },
        );
        // Discover custom fields.
        $t_related_custom_field_ids = custom_field_get_linked_ids($bug->project_id);
        foreach ($t_related_custom_field_ids as $t_id) {
            $t_def = custom_field_get_definition($t_id);
            $values['custom_' . $t_def['name']] = function ($bug) use ($t_id) {
                return custom_field_get_value($t_id, $bug->id);
            };
        }
        if (isset($values[$field_name])) {
            $func = $values[$field_name];
            return $func($bug);
        } else {
            return FALSE;
        }
    }

    function get_channel($project)
    {
        $channels = plugin_config_get('channels');
        return array_key_exists($project, $channels) ? $channels[$project] : plugin_config_get('default_channel');
    }

    function get_webhook($project)
    {
        $webhooks = plugin_config_get('url_webhooks');
        return array_key_exists($project, $webhooks) ? $webhooks[$project] : plugin_config_get('url_webhook');
    }

    function notify($msg, $webhook, $channel, $attachment = FALSE)
    {
        if ($this->skip) return;
        if (empty($channel)) return;
        if (empty($webhook)) return;

        $ch = curl_init();
        // @see https://my.slack.com/services/new/incoming-webhook
        // remove istance and token and add plugin_Slack_url config , see configurations with url above
        $url = sprintf('%s', trim($webhook));
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $payload = array(
            'channel' => $channel,
            'username' => plugin_config_get('bot_name'),
            'text' => $msg,
            'link_names' => plugin_config_get('link_names'),
        );
        $bot_icon = trim(plugin_config_get('bot_icon'));
        if (empty($bot_icon)) {
            $payload['icon_url'] = 'https://raw.githubusercontent.com/infojunkie/MantisBT-Slack/master/mantis_logo.png';
        } elseif (preg_match('/^:[a-z0-9_\-]+:$/i', $bot_icon)) {
            $payload['icon_emoji'] = $bot_icon;
        } elseif ($bot_icon) {
            $payload['icon_url'] = trim($bot_icon);
        }
        if ($attachment) {
            $payload['attachments'] = array($attachment);
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $result = curl_exec($ch);
        if ($result !== 'ok') {
            trigger_error(curl_errno($ch) . ': ' . curl_error($ch), E_USER_WARNING);
            plugin_error('ERROR_CURL', E_USER_ERROR);
        }
        curl_close($ch);

        return $result;
    }

    function bbcode_to_slack($bbtext)
    {
        $bbtags = array(
            '[b]' => '*', '[/b]' => '* ',
            '[i]' => '_', '[/i]' => '_ ',
            '[u]' => '_', '[/u]' => '_ ',
            '[s]' => '~', '[/s]' => '~ ',
            '[sup]' => '', '[/sup]' => '',
            '[sub]' => '', '[/sub]' => '',

            '[list]' => '', '[/list]' => "\n",
            '[*]' => '• ',

            '[hr]' => "\n———\n",

            '[left]' => '', '[/left]' => '',
            '[right]' => '', '[/right]' => '',
            '[center]' => '', '[/center]' => '',
            '[justify]' => '', '[/justify]' => '',
        );

        $bbtext = str_ireplace(array_keys($bbtags), array_values($bbtags), $bbtext);

        $bbextended = array(
            "/\[code(.*?)\](.*?)\[\/code\]/is" => "```$2```",
            "/\[color(.*?)\](.*?)\[\/color\]/is" => "$2",
            "/\[size=(.*?)\](.*?)\[\/size\]/is" => "$2",
            "/\[highlight(.*?)\](.*?)\[\/highlight\]/is" => "$2",
            "/\[url](.*?)\[\/url]/i" => "<$1>",
            "/\[url=(.*?)\](.*?)\[\/url\]/i" => "<$1|$2>",
            "/\[email=(.*?)\](.*?)\[\/email\]/i" => "<mailto:$1|$2>",
            "/\[img\]([^[]*)\[\/img\]/i" => "<$1>",
        );

        foreach ($bbextended as $match => $replacement) {
            $bbtext = preg_replace($match, $replacement, $bbtext);
        }
        $bbtext = preg_replace_callback("/\[quote(=)?(.*?)\](.*?)\[\/quote\]/is",
            function ($matches) {
                if (!empty($matches[2]))
                    $result = "\n> _*" . $matches[2] . "* wrote:_\n> \n";
                $lines = explode("\n", $matches[3]);
                foreach ($lines as $line)
                    $result .= "> " . $line . "\n";
                return $result;
            }, $bbtext);
        return $bbtext;
    }

    function get_user_name($user_id)
    {
        $user = user_get_row($user_id);
        $username = $user['username'];
        $usernames = plugin_config_get('usernames');
        $username = array_key_exists($username, $usernames) ? $usernames[$username] : $username;
        return '<@' . $username . '>';
    }


    //----------------- Imatic Changes --------------
    function bug_view_details()
    {
        if (isset($_GET['id'])) {
            $issue_id = $_GET['id'];
            $issue = bug_get_row($issue_id);

            $assignedUser = $this->userHasAssignedChannel($issue['handler_id']);
            if (!$assignedUser) {
                echo '<a id="notifyToSlack" class="disabled btn btn-primary btn-white btn-round btn-sm" href="">
                    <img id="slackLogoButton" src="' . plugin_file("slack-icon.png") . '" alt="">
                    ' . plugin_lang_get('imatic_user_does_not_has_assigned_channel') . '
                </a>';
                return false;
            }

            echo '<a id="notifyToSlack" class="btn btn-primary btn-white btn-round btn-sm" href="' . plugin_page('send_slack_reminder') . '&id=' . $issue_id . '">
                 <img id="slackLogoButton" src="' . plugin_file("slack-icon.png") . '" alt="">
                  ' . plugin_lang_get("imatic_send_slack_reminder") . '
                 <img style="display:none" id="slackNotificationIcon" src="' . plugin_file("img/notification.png") . '" alt="">
                 <img style="display:none" id="slackNotificationIconError" src="' . plugin_file("img/error-notification.png") . '" alt="">
        </a>';
        }
    }

    public function layout_body_end_hook($p_event)
    {

        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
        $url = $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

        $file = basename(parse_url($url, PHP_URL_PATH));


        $t_data = htmlspecialchars(json_encode([
            'imatic_button_reminder_settings' => plugin_config_get('imatic_button_reminder_settings'),
            'imatic_users_with_assigned_channels' => plugin_config_get('imatic_users_with_assigned_channels'),
            'imatic_text_after_recipient_if_has_channel' => plugin_config_get('imatic_text_after_recipient_if_has_channel'),
        ]));


        echo '<script id="imaticSlackData" data-data="' . $t_data . '" src="' . plugin_file('imatic-slack.js') . '&v=' . $this->version . '"></script>
            <link rel="stylesheet" type="text/css" href="' . plugin_file('css/imatic-slack.css') . '&v=' . $this->version . '" />
            ';

    }

    public function addChanel($data)
    {
        $channels = plugin_config_get('imatic_channels');

        $id = uniqid();
        $channels[] = [
            'id' => $id,
            'channel_name' => $_POST['channel_name'],
            'channel_webhook_url' => $_POST['channel_webhook_url']
        ];
        plugin_config_set('imatic_channels', $channels);


        return true;
    }

    public function deleteChannel($id)
    {
        $channels = (array)plugin_config_get('imatic_channels');

        $key = $this->searchForId($id, $channels);

        unset($channels[$key]);

        plugin_config_set('imatic_channels', $channels);

        return true;

    }

    public function assignChannel($data)
    {

        $user_id = user_get_id_by_name($data['user_name']);
        $channelId = $this->getChannelName($_POST['channel_id']);
        $channelUrl = $this->getChannelUrl($_POST['channel_id']);

        if (!$user_id) return;
        if (!$channelId) return;
        if ($this->userHasAssignedChannel($user_id)) return;

        $id = uniqid();
        $assigned = plugin_config_get('imatic_assigned_channels');

        $assigned[] = [
            'id' => $id,
            'username' => $_POST['user_name'],
            'user_id' => $user_id,
            'channel_id' => $_POST['channel_id'],
            'channel_name' => $channelId,
            'channel_webhook_url' => $channelUrl
        ];

        plugin_config_set('imatic_assigned_channels', $assigned);

        return true;

    }

    public function deleteAssignChannel($id)
    {

        $assigned = (array)plugin_config_get('imatic_assigned_channels');

        $key = $this->searchForId($id, $assigned, 'id');

        unset($assigned[$key]);

        plugin_config_set('imatic_assigned_channels', $assigned);

        return true;

    }

    public function searchForId($id, $array)
    {
        foreach ($array as $key => $val) {
            if ($val['id'] === $id) {
                return $key;
            }
        }
        return null;
    }

    public function getChannelName($channelId)
    {
        $channels = (array)plugin_config_get('imatic_channels');

        foreach ($channels as $key => $val) {
            if ($val['id'] === $channelId) {
                return $val['channel_name'];
            }
        }

        return false;
    }

    public function getChannelUrl($channelId)
    {
        $channels = (array)plugin_config_get('imatic_channels');

        foreach ($channels as $key => $val) {
            if ($val['id'] === $channelId) {
                return $val['channel_webhook_url'];
            }
        }

        return false;
    }

    public function getChannelUrlByReporterId($reporterId)
    {
        $assigned = (array)plugin_config_get('imatic_assigned_channels');


        foreach ($assigned as $key => $val) {
            if ((int)$val['user_id'] === (int)$reporterId) {
                return $val['channel_webhook_url'];
            }
        }
        return false;
    }

    public function userHasAssignedChannel($user_id)
    {
        $assigned = (array)plugin_config_get('imatic_assigned_channels');

        $key = array_search($user_id, array_column($assigned, 'user_id'));

        if ($key !== false) {
            return true;
        } else {
            return false;
        }

    }

    public function getAllUsersWithAssignedChannel()
    {
        $assigned = (array)plugin_config_get('imatic_assigned_channels');

        $users = [];
        foreach ($assigned as $assign) {
            if (isset($assign['user_id'])) {
                $users[] = $assign['user_id'];
            }
        }

        return $users;
    }

    public function event_core_ready()
    {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
        $url = $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

        $file = basename(parse_url($url, PHP_URL_PATH));

        if ($file === 'bug_reminder.php') {

            if (isset($_POST) && !empty($_POST)) {
                if (isset($_POST['slack_notify']) && $_POST['slack_notify'] == true) {
                    $usersTo = $_POST['to'];
                    $bug = bug_get($_POST['bug_id']);

                    $summary = plugin_get()->format_summary($bug);

                    foreach ($usersTo as $userId) {
                        $url = string_get_bug_view_url_with_fqdn($_POST['bug_id']);
                        $webhook = plugin_get()->getChannelUrlByReporterId($userId);
                        $reminderedName = user_get_name($userId);

                        $bugText = '<@' . $reminderedName . '>' . ' ' . $_POST['bugnote_text'];

                        $msg = sprintf(
                                plugin_lang_get('imatic_bug_reminder_page_message'),
                                $url,
                                $summary
                            ) . ':' . "\n" . $bugText;

                        if ($webhook) {
                            $this->notify($msg, $webhook, plugin_get()->get_attachment($bug));
                        }
                    }
                }
            }
        }
    }
}

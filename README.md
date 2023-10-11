MantisBT-Slack
==============

A [MantisBT](http://www.mantisbt.org/) plugin to send bug updates to [Slack](https://slack.com/), [Mattermost](https://about.mattermost.com/) and [Discord](https://discord.com/) channels.

# Setup
* The `master` branch requires Mantis 2.x, while the `master-1.2.x` branch works for Mantis 1.2.x.
* Extract this repo to your *Mantis folder/plugins/Slack*.
* On the Slack side, add a new "Incoming Webhooks" integration and note the URL that Slack generates for you.
* On the MantisBT side, access the plugin's configuration page and fill in your Slack webhook URL.
* You can map your MantisBT projects to Slack channels by setting the *plugin_Slack_channels* option in Mantis.  Follow the instructions on the plugin's configuration page to get there. Make sure the *plugin_Slack_channels* configuration option is set to "All Users", with type "complex".
    Example value for this setting:

            array (
              'My First Mantis Project' => '#general',
              'My Second Mantis Project' => '#second-project'
            )

* You can specify which bug fields appear in the Slack notifications. Edit the *plugin_Slack_columns* configuration option for this purpose.  Follow the
instructions on the plugin configuration page.

* For Discord, you need to append `/slack` so that Discord handles this as a Slack-compatible webhook. [More info here](https://discord.com/developers/docs/resources/webhook#execute-slackcompatible-webhook).

# Development
You can run a local development environment using Docker Compose:
- `docker-compose build && docker-compose up`
- Open http://localhost:8080/admin/install.php and install the database (using Admin credentials `root` / `root`)
- Login to Mantis using `administrator` / `root`
- Enable Slack plugin at http://localhost:8080/manage_plugin_page.php

### Imatic It Changes
The Imatic changes is a tool that enables users to create and assign channels.
- 'imatic_text_after_recipient_if_has_channel': The text displayed after the recipient if they have an assigned channel. In this case, it displays the text with the Message icon: '( &#128172;  Slack )'.
- 'imatic_text_after_recipient_if_has_not_channel': The text displayed after the recipient if they do not have an assigned channel. In this case, it displays the text with the Slack icon and a cross mark: '( &#10060;  Slack )'.
- 'imatic_button_reminder_settings': Settings for the Imatic reminder button on the remind page :
    - 'text': The text on the button, you can change this text in lang.
    - 'iconSrc': The source of the button icon,
    - 'disable_if_user_not_have_assign_channel': Setting indicating whether the reminder button is disabled for users who do not have an assigned Imatic channel. In this case, it is set to false, which means the button will always be enabled.

### Configuration on plugin page
- The Imatic changes is a tool that enables users to create and assign channels.
- Channel Creation:
  - Users can create new channels by providing a name and a corresponding URL.
- Channel List:
  - The plugin provides an overview of existing channels, including their names and URLs. This list allows users to manage and modify channels as needed.
- Channel Assignment:
  - Users can assign channels to specific users based on their usernames. Each user can be assigned only one channel.
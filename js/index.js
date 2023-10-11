$(document).ready(function () {

    function getSettings() {
        const el = document.querySelector("#imaticSlackData");
        if (el == null) {
            return;
        }

        return JSON.parse(el.dataset.data);
    }

    const settings = getSettings()

    var button = $("a[href='#history']");
    var newButton = $("#notifyToSlack");

    // Move button
    button.appendTo(button.parent());
    button.after(newButton);

    newButton.click(function (event) {
        event.preventDefault();
        event.stopPropagation();


        let logo = $("#slackLogoButton")

        var rotation = 0;
        var interval = setInterval(function () {
            logo.css({
                transform: "rotate(" + rotation + "deg)"
            });
            rotation += 10;
        }, 50);

        $.ajax({
            url: $(this).attr('href'),
            method: 'POST',
            success: function (response) {
                if (response.status == 'success') {
                    $("#slackNotificationIcon").fadeIn().delay(1500).fadeOut();

                    clearInterval(interval);
                    logo.css({transform: "rotate(0deg)"});
                } else {

                    $("#slackNotificationIconError").fadeIn().delay(1500).fadeOut();

                    clearInterval(interval);
                    logo.css({transform: "rotate(0deg)"});
                }
            },
            error: function (jqXHR, textStatus, errorThrown) {
                console.log(textStatus + ': ' + errorThrown);
            }
        });
    });

    toggleList($('#show-channel-list'), $('#channel-list'))
    toggleList($('#show-assigned-list'), $('#assigned-list'))

    function toggleList(button, list) {
        button.click(function (e) {
            e.preventDefault();
            if (list.is(':visible')) {
                list.hide();
                $(this).find("i").toggleClass("fa-eye fa-eye-slash");
            } else {
                list.show();
                $(this).find("i").toggleClass("fa-eye-slash fa-eye");
            }
        });
    }


    // Bug reminder notification

    const form = $('form[action="bug_reminder.php"]')

    form.each(function () {
        var form = $(this);


        var submitButton = form.find('input[type="submit"]');

        var iconImageSrc = settings.imatic_button_reminder_settings.iconSrc;
        var buttonText = settings.imatic_button_reminder_settings.text;

        var siblingButton = $('<button>').attr({
            'type': 'submit',
            'id': 'reminder-page-button',
            'class': 'btn btn-primary btn-white btn-round margin-left-8'
        });

        var iconImage = $('<img>').attr('src', iconImageSrc).css('margin-right', '5px'); // Zmeniť hodnotu podľa potreby

        siblingButton.append(iconImage);

        siblingButton.append(buttonText);

        submitButton.after(siblingButton);


        siblingButton.on('click', function (e) {
            e.preventDefault()

            form.append($('<input>').attr({
                'type': 'hidden',
                'name': 'slack_notify',
                'value': 'true'
            }));


            form.submit();
        })
    })


    // Show icon after username if user has channel
    const recipientElement = $("#recipient");
    if (recipientElement.length > 0) {
        const recipients = $("#recipient option")

        recipients.each(function () {
            const _this = $(this)
            const imaticUsersWithAssignedChannels = settings.imatic_users_with_assigned_channels
            const imaticTextAfterRecipientIfHasChannel = settings.imatic_text_after_recipient_if_has_channel

            const recipientId = parseInt($(this).val())
            var text = _this.text();

            if (userHasAssignChannel(imaticUsersWithAssignedChannels, recipientId)) {
                _this.html(text + ' ' + imaticTextAfterRecipientIfHasChannel);
            }
        });


        // Check users if has assigned channel
        var selectElement = document.getElementById("recipient");
        var selectedOptions = [];

        var options = selectElement.options;
        for (var i = 0; i < options.length; i++) {
            options[i].addEventListener("click", function () {
                selectedOptions = Array.from(options)
                    .filter(option => option.selected)
                    .map(option => option.value);

                var usersWithAssignChannel = selectedOptions.filter(function (userId) {
                    return userHasAssignChannel(settings.imatic_users_with_assigned_channels, userId);
                });

                var allUsersHaveAssignChannel = usersWithAssignChannel.length === selectedOptions.length;

                var slackButton = $('#' + settings.imatic_button_reminder_settings.id);

                // If is in plugin config is true button be disabled if one of users does not have channel
                if (settings.imatic_button_reminder_settings.disable_if_user_not_have_assign_channel == true) {
                    if (!allUsersHaveAssignChannel) {
                        slackButton.prop("disabled", true);
                    } else {
                        slackButton.prop("disabled", false);
                    }
                }
            });
        }

    }
    // End Check users if has assigned channel

    function userHasAssignChannel(imaticUsersWithAssignedChannels, userId) {
        userId = parseInt(userId)

        imaticUsersWithAssignedChannels = imaticUsersWithAssignedChannels.map(function(value) {
            return parseInt(value);
        });

        return Object.values(imaticUsersWithAssignedChannels).includes(userId);

    }
})
;

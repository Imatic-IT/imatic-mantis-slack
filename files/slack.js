$(document).ready(function () {

    var button = $("a[href='#history']");
    var newButton = $("#notifyToSlack");

    // Move button
    button.appendTo(button.parent());
    button.after(newButton);


    var notificationVisible = false;
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

        var url = $(this).attr('href');
        var notificationVisible = false;

        $.ajax({
            url: $(this).attr('href'),
            method: 'POST',
            success: function (response) {
                if (response.status == 'success') {
                    $("#slackNotificationIcon").fadeIn().delay(1500).fadeOut();

                    if (!notificationVisible) {
                        notificationVisible = true;
                        $("#my-notification").fadeIn(1500, function () {
                            $(this).delay(1500).fadeOut(1500, function () {
                                notificationVisible = false;
                            });
                        });
                    }

                    clearInterval(interval);
                    logo.css({ transform: "rotate(0deg)" });
                } else {

                    $("#slackNotificationIconError").fadeIn().delay(1500).fadeOut();

                    if (!notificationVisible) {
                        notificationVisible = true;
                        $("#my-notification").fadeIn(1500, function () {
                            $(this).delay(1500).fadeOut(1500, function () {
                                notificationVisible = false;
                            });
                        });
                    }

                    clearInterval(interval);
                    logo.css({ transform: "rotate(0deg)" });
                }

            },
            error: function (jqXHR, textStatus, errorThrown) {
                console.log(textStatus + ': ' + errorThrown); // chybová správa
            }
        });
    });


    $('#show-channel-list').click(function (e) {
        e.preventDefault();
        var channelList = $('#channel-list');
        if (channelList.is(':visible')) {
            channelList.hide();
            $(this).find("i").toggleClass("fa-eye fa-eye-slash");


        } else {
            channelList.show();
            $(this).find("i").toggleClass("fa-eye-slash fa-eye");
        }
    });

    $('#show-assigned-list').click(function (e) {
        e.preventDefault();
        var assignedList = $('#assigned-list');
        if (assignedList.is(':visible')) {
            assignedList.hide();
            $(this).find("i").toggleClass("fa-eye fa-eye-slash");
        } else {
            assignedList.show();
            $(this).find("i").toggleClass("fa-eye-slash fa-eye");
        }
    });

})
;

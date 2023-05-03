$(document).ready(function () {

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
            url:  $(this).attr('href'),
            method: 'POST',
            success: function (response) {
                if (response.status == 'success') {
                    $("#slackNotificationIcon").fadeIn().delay(1500).fadeOut();

                    clearInterval(interval);
                    logo.css({ transform: "rotate(0deg)" });
                } else {

                    $("#slackNotificationIconError").fadeIn().delay(1500).fadeOut();

                    clearInterval(interval);
                    logo.css({ transform: "rotate(0deg)" });
                }

            },
            error: function (jqXHR, textStatus, errorThrown) {
                console.log(textStatus + ': ' + errorThrown); // chybová správa
            }
        });
    });

    toggleList($('#show-channel-list'),$('#channel-list'))
    toggleList($('#show-assigned-list'),$('#assigned-list'))

    function  toggleList(button, list){
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

})
;

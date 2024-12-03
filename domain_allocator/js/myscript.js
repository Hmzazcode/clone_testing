(function ($) {

    var wordpressInstanceForm = document.getElementById('transferForm');
    var id = getIdFromUrl('id');

    if (wordpressInstanceForm) {
        wordpressInstanceForm.addEventListener('submit', function (event) {
            event.preventDefault();

            var formData = new FormData(this);
            formData.append('allocator_ajax_command', 'yes');
            formData.append('id', id);

            var xhr = new XMLHttpRequest();
            xhr.open('POST', '/modules/addons/domain_allocator/function.php', true);

            document.getElementById('loader').style.display = 'block';

            xhr.onload = function (){

                if (xhr.status == 200) {

                    //document.getElementById('loader').style.display = 'none';

                    var responseData = JSON.parse(xhr.responseText);

                    if (responseData.status == 'success') {

                        $('#alert').removeClass('alert-danger');
                        $('#alert').addClass(responseData.alertClass);
                        $('#alert span:first').text(responseData.message);
                        $('#alert').show();

                    } else {

                        $('#alert').removeClass('alert-success');
                        $('#alert').addClass(responseData.alertClass);
                        $('#alert span:first').text(responseData.message);
                        $('#alert').show();

                    }
                }

            };
            xhr.send(formData);
        });
    }

    $('#close-button').click(function () {
        $.ajax({
            success: function (response) {
                document.getElementById('alert').style.display = 'none';
            },
            error: function (xhr, status, error) {
                console.error('AJAX error:', error);
            }
        });
    });

    function getIdFromUrl(param) {
        var urlParams = new URLSearchParams(window.location.search);
        return urlParams.get(param);
    }
})(jQuery);
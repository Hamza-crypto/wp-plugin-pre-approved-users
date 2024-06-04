jQuery(document).ready(function($) {
    $('.paer-delete-email').on('click', function(e) {
        e.preventDefault();
        
        var email = $(this).data('email');
        var nonce = $(this).data('nonce');
        
        if (confirm("Are you sure you want to delete the email '" + email + "'?")) {
            $.ajax({
                url: paer_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'paer_delete_email',
                    email: email,
                    nonce: nonce
                },
                success: function(response) {
                    console.log(response);  // Log the response for debugging
                    if (response.success) {
                        $('tr[data-email="' + email + '"]').remove();
                        //alert('Email deleted successfully.');
                    } else {
                        var errorMessage = response.data && response.data.message ? response.data.message : 'An error occurred.';
                        alert('Error: ' + errorMessage);
                    }
                },
                error: function() {
                    alert('An error occurred while processing the request.');
                }
            });
        }
    });
});

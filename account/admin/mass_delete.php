<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/config.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/libs/permissions.class.php');

global $link;

$perm = new Permission();

if (!$perm->isAdmin()) {
    header("location: /login");
    $link->close();
    exit;
}

$link->close();

if ($_SERVER["REQUEST_METHOD"] == "GET") :
?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <title>Mass Delete</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    </head>

    <body>
        <div class="container">
            <h2>Mass Delete</h2>
            <form id="mass_delete_form">
                <div class="form-group">
                    <label for="file_list">File List:</label>
                    <textarea class="form-control" id="file_list" name="file_list" rows="10"></textarea>
                </div>
                <button type="submit" class="btn btn-primary">Submit</button>
            </form>
            <div id="response" class="mt-3"></div>
        </div>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                document.getElementById('mass_delete_form').addEventListener('submit', function(e) {
                    e.preventDefault();
                    var formData = new FormData(this);
                    fetch('/api/v2/admin/media/mass-delete', {
                            method: 'POST',
                            credentials: 'same-origin',
                            body: formData
                        })
                        .then(function(response) {
                            if (response.ok) {
                                return response.json();
                            } else {
                                throw new Error('An error occurred');
                            }
                        })
                        .then(function(data) {
                            if (data.success) {
                                document.getElementById('response').innerHTML = '<div class="alert alert-success" role="alert">Files deleted successfully</div>';
                                document.getElementById('file_list').value = '';
                                document.getElementById('response').innerHTML += '<div class="alert alert-info" role="alert">Files deleted: ' + data.deleted + '</div>';
                            } else {
                                document.getElementById('response').innerHTML = '<div class="alert alert-danger" role="alert">Error: ' + data.error + '</div>';
                            }
                        })
                        .catch(function(error) {
                            document.getElementById('response').innerHTML = '<div class="alert alert-danger" role="alert">' + error.message + '</div>';
                        });
                });
            });
        </script>
    </body>

    </html>
<?php
endif;

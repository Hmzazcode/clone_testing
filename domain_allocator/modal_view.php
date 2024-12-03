<?php
use WHMCS\Database\Capsule;
?>

<link rel="stylesheet" href="modules/addons/domain_allocator/css/style.css">

<div class="modal fade domain_allocator" id="Mailing_List_Subscription_Prefs" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content shadow-none">
            <div class="modal-header">
                <h5 class="modal-title" id="exampleModalLabel">Assign <b>' <?php print($domain) ; ?>  '</b> To Web Hosting Account</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <?php echo $hostingLocation ?>
                <div class="clients_hostings">
                    <form id="transferForm">
                        <label>Web Hosting Account </label><br>
                        <select class="form-control" name="webHosting" id="webHosting"><?php echo $hostingAccounts ?></select><br>
                        <label>Assignment Type</label><br>
                        <select class="form-control" name="assignmentType" id="assignmentType">
                            <option>Addon domain</option>
                            <option>Parked/Alias domain</option>
                        </select><br>
                        <div class="form-check">
                            <input class="form-check-input" name="check" type="checkbox" id="domainNameserver">
                            <label class="form-check-label" for="domainNameserver">
                                Change Domain Nameservers
                            </label>
                        </div>
                            <br>
                        <div id="alert" class="alert" style="display: none;">
                            <span></span>
                            <button style="top: 1px;" type="button" id="close-button">
                                <span id="cross"><i class="fas fa-times"></i></span>
                            </button>
                        </div>
                        <div class="text-center d-block mt-5">
                            <button class="btn btn-success" type="submit"><span id="loader" style="display: none;" class="hidden">
                                <i class="fas fa-spinner fa-spin" ></i></span>
                                    Transfer
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
<script src="/modules/addons/domain_allocator/js/myscript.js"></script>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
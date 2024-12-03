<?php
use WHMCS\Database\Capsule;

$licensekey = $localkey = '';
$params_ajax = false;

$licensekey = Capsule::table('tbladdonmodules')
    ->where('module', 'domain_allocator')
    ->where('setting', 'License Key')
    ->value('value');

$results = domainAllocatorCheckLicense($licensekey, $localkey, $params_ajax);

if ($results['status'] == "Active"){ ?>

    <h1> Logs </h1>

    <?php
    $users = Capsule::table('tblmodulelog')->where('module', 'Assign To Hosting')->get();
    foreach ($users as  $row){
        $data[] = $row;
    }
    ?>

    <table class="table" style="">
        <thead>
        <tr>
            <th scope="col">Date</th>
            <th scope="col">Module</th>
            <th scope="col">Action</th>
            <th scope="col">Request</th>
            <th scope="col">Response</th>
        </tr>
        </thead>

        <tbody>
        <?php foreach ($data as $key => $row2){ ?>
            <tr>
                <th><?php echo $row2->date;?></th>
                <td><?php echo $row2->module;?></td>
                <td><?php echo $row2->action;?></td>
                <td><?php echo $row2->request;?></td>
                <td>
                    <div style="overflow-y:scroll; overflow-x: hidden; height:100px !important; width: 500px">
                        <?php echo $row2->response; ?>
                    </div>
                </td>
            </tr>
        <?php } ?>
        </tbody>
    </table>

<?php
}
?>
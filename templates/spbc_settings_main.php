<?php
    $t_last_attacks_tpl = <<<EOT
<div class="spbc_table_general">
<table border="0" class="spbc_table_general">
    <tr>
        <th>
           Date and time 
        </th>
        <th>
            User
        </th>
        <th>
            Action 
        </th>
        <th>
            IP, Country
        </th>
    </tr>
    %s
</table>
</div>
EOT;

    $row_last_attacks_tpl = <<<EOT
    <tr>
        <td>
            %s 
        </td>
        <td>
            %s 
        </td>
        <td>
            %s 
        </td>
        <td>
            %s 
        </td>
    </tr>

EOT;

$spbc_tpl = array_merge($spbc_tpl, array(
    't_last_attacks_tpl' => $t_last_attacks_tpl,
    'row_last_attacks_tpl' => $row_last_attacks_tpl,
));


?>
<div class="row">
    <div class="col-lg-12">
        <div class="panel panel-danger">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <?php
                        if (isset($_REQUEST['protocolo'])) {
                            switch ($_REQUEST['protocolo']) {
                                case 'EmAndamento':
                                    echo "Protocolos Finalizados";
                                case 'Finalizado':
                                    echo "Protocolos Finalizados";
                            }
                        };             
                    ?>
                </div>            
                <div class="panel-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered table-hover" id="dataTables-example">
                            <thead>
                                <tr>
                                    <th>Protocolo</th>
                                    <th>Nome</th>
                                    <th>Tipo Serviço</th>
                                    <th>Responsável</th>
                                    <th>Data Abertura</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php
                                include ('./Comandos/comandos.php');  
                                include ('./Config/conexao.php');
                                ini_set('default_charset', 'UTF-8');
                                                             
                                if (isset($_REQUEST['protocolo'])) {
                                    switch ($_REQUEST['protocolo']) {
                                        case 'EmAndamento':
                                            $sqlTable = mysqli_query($mysqli, $comandoSQL_ProtocolosEmAndamento); 
                                        case 'Finalizado':
                                            $sqlTable = mysqli_query($mysqli, $comandoSQL_ProtocolosFinalizado);
                                    }
                                };           
/*case 'NFE1':
    $sqlTable = mysqli_query($mysqli, $comandoSql_EmissorNFeVersaoOld);
    break;
case 'NFCE1':
    $sqlTable = mysqli_query($mysqli, $comandoSql_EmissorNFCeVersaoOld);
    break;
case 'parc1':
    $sqlTable = mysqli_query($mysqli, $comandoSQL_BackupAtrasadoLaercio);                                                      


                                                         

 case 'bkp02':
    $sqlTable = mysqli_query($mysqli, $comandoSql_SelectBackupNOK);                                                      
    break;*/



                    $sqlTable = mysqli_query($mysqli, $comandoSQL_BuscaPorVersao);



                                
                                
                                
                                
                                
                                
                                
                                
                                
                                
                                
                                
                                while ($linha = mysqli_fetch_array($sqlTable)) {                                            
                            ?>     
                            <tr class="odd gradeX">
                                <td><?php echo $linha['01']; ?></td>
                                <td><?php echo $linha['02']; ?></td>
                                <td><?php echo $linha['03']; ?></td>
                                <td class="center"><?php echo $linha['04']; ?></td>
                                <td class="center"><?php echo $linha['05']; ?></td>
                            </tr>
                            <?php }?>
                        </tbody>
                    </table>
                </div>                           
            </div>
        </div>
    </div>
</div>
        



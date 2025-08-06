<div class="inner">
    <div class="row">
        <div class="col-lg-12">
            <h2> Relátorios </h2>
        </div>
    </div>
    <div class="row">
        <div class="col-lg-12">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <?php
                        if (isset($_REQUEST['pro'])) {
                            if ($_REQUEST['pro'] == "EmAndamento"){
                                echo "Protocolos em Andamentos";
                            }else if ($_REQUEST['pro'] == "Finalizado"){
                                echo "Protocolos Finalizados";                                   
                            }else if ($_REQUEST['pro'] == "EmAberto"){
                                echo "Protocolos em Aberto";                                   
                            }else if ($_REQUEST['pro'] == "nfeon"){
                                echo "Emissor NFe Online";
                            }else if ($_REQUEST['pro'] == "nfeoff"){
                                echo "Emissor NFe Offline";                            
                            }else if ($_REQUEST['pro'] == "nfceon"){
                                echo "Emissor NFCe Online";
                            }else if ($_REQUEST['pro'] == "nfceoff"){
                                echo "Emissor NFCe Offline";                            
                            }else if ($_REQUEST['pro'] == "backupok"){
                                echo "Clientes com backup em dia ";                            
                            }else if ($_REQUEST['pro'] == "backupnok"){
                                echo "Clientes com backup em atraso (mais de 2 dias)"; 
                            }else if ($_REQUEST['pro'] == "finnok"){
                                echo "Clientes Ativos inadiplentes ";                            
                            }else if ($_REQUEST['pro'] == "finok"){
                                echo "Clientes inadiplentes Bloqueados"; 
                            }else if ($_REQUEST['pro'] == "finlib"){
                                echo "Clientes inadiplentes Liberados"; 
                            }else if ($_REQUEST['pro'] == "empok"){
                                echo "Clientes Atualizados - Empresariial";                            
                            }else if ($_REQUEST['pro'] == "empnok"){
                                echo "Clientes Desatualizados - Empresarial"; 
                            }else if ($_REQUEST['pro'] == "traok"){
                                echo "Clientes Atualizados - Transportadoras";                            
                            }else if ($_REQUEST['pro'] == "tranok"){
                                echo "Clientes Desatualizados - Transportadoras"; 
                            }else if ($_REQUEST['pro'] == "agrok"){
                                echo "Clientes Atualizados - Agricola";                            
                            }else if ($_REQUEST['pro'] == "agrnok"){
                                echo "Clientes Desatualizados - Agricola"; 
                            }else if ($_REQUEST['pro'] == "ftpok"){
                                echo "Clientes Atualizados - Aplicação Calory FTP"; 
                            }else if ($_REQUEST['pro'] == "ftpnok"){
                                echo "Clientes Desatualizados - Aplicação Calory FTP"; 
                            }else if ($_REQUEST['pro'] == "updok"){
                                echo "Clientes Atualizados - Aplicação Calory Update"; 
                            }else if ($_REQUEST['pro'] == "updnok"){
                                echo "Clientes Desatualizados - Aplicação Calory Update"; 
                            }else if($_REQUEST['pro'] == "bolok"){
                                echo "Clientes com boletos";  
                            }else if($_REQUEST['pro'] == "bolnok"){
                                echo "Clientes sem boletos";  
                        }else if($_REQUEST['pro'] == "cm113_A"){
                                echo "Calory Manager - Clientes Atualizado";  
                        }else if($_REQUEST['pro'] == "cm113_On"){
                                echo "Calory Manager - Clientes Online";  
                        }else if($_REQUEST['pro'] == "cm113_Off"){
                                echo "Calory Manager - Clientes Offline";  
                        }else if($_REQUEST['pro'] == "cm113_Atz"){
                                echo "Calory Manager - Clientes para Atualizar";  
                        };             
                        };
                    ?>                                     
                </div>
                <div class="panel-body">
                 <?php   
                    if ($_REQUEST['pro'] == "backupnok"){
                        echo "<div style='text-align: center;'>".
                             "<a href='index.php?page=rel&pro=backupnok&pri=1' class='btn btn-danger btn-line' >Urgente</a>".                      
                             "<a href='index.php?page=rel&pro=backupnok&pri=2' class='btn btn-warning btn-line'>Alto   </a>".
                             "<a href='index.php?page=rel&pro=backupnok&pri=3' class='btn btn-success btn-line'>Baixo  </a>".
                             "<a href='index.php?page=rel&pro=backupnok&pri=4' class='btn btn-info btn-line'   >Normal </a>".
                             "<a href='index.php?page=rel&pro=backupnok&pri=5' class='btn btn-default btn-line'>Nuvem3 </a>".
                             "</div>".
                             "<hr/>";
                             };       ?>   
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered table-hover" id="dataTables-example">
                            <thead>
                                <tr>
                                    <?php    
                                        if (isset($_REQUEST['pro'])) {
                                            if ($_REQUEST['pro'] == "EmAndamento"){
                                                echo "<th>Protocolo</th><th>Nome</th><th>Tipo Serviço</th><th>Responsável</th><th>Data Abertura</th>";
                                            }else if ($_REQUEST['pro'] == "Finalizado"){
                                                echo "<th>Protocolo</th><th>Nome</th><th>Tipo Serviço</th><th>Responsável</th><th>Data Abertura</th>";                                   
                                            }else if ($_REQUEST['pro'] == "EmAberto"){
                                                echo "<th>Protocolo</th><th>Nome</th><th>Tipo Serviço</th><th>Responsável</th><th>Data Abertura</th>";                                   
                                            }else if (($_REQUEST['pro'] == "nfeon")  or 
                                                      ($_REQUEST['pro'] == "nfeoff") or
                                                      ($_REQUEST['pro'] == "nfceon") or
                                                      ($_REQUEST['pro'] == "nfceoff")){
                                                echo "<th>#</th><th>Nome</th><th>Versão</th><th class='center' >Versão NFe</th><th>Último Backup</th>";                                   
                                            }else if (($_REQUEST['pro'] == "backupok") or 
                                                       ($_REQUEST['pro'] == "backupnok")){
                                                echo "<th>#</th><th>Aplicação</th><th>Nome</th><th class='center' >Último Backup</th><th>Dias Atraso</th>";                                   
                                            }else if (($_REQUEST['pro'] == "finnok") or 
                                                      ($_REQUEST['pro'] == "finok")  or
                                                      ($_REQUEST['pro'] == "finlib")){
                                                echo "<th>#</th><th>Nome</th><th>Ativo</th><th class='center' >Bloquear</th><th>Saldo Devedor</th><th>Data Acerto</th><th>Versão</th>";                                   
                                            }else if (($_REQUEST['pro'] == "empok")  or 
                                                      ($_REQUEST['pro'] == "empnok") or 
                                                      ($_REQUEST['pro'] == "traok")  or 
                                                      ($_REQUEST['pro'] == "tranok") or
                                                      ($_REQUEST['pro'] == "agrok")  or 
                                                      ($_REQUEST['pro'] == "agrnok")){
                                                echo "<th>#</th><th>Nome</th><th>Versão</th><th class='center' >Cidade</th><th>Último Backup</th>";
                                            }else if (($_REQUEST['pro'] == "ftpok")  or 
                                                      ($_REQUEST['pro'] == "ftpnok")){
                                                echo "<th>#</th><th>Nome</th><th cass='center'>Versão</th><th class='center' >Versão FTP</th><th>Pasta</th>";
                                            }else if (($_REQUEST['pro'] == "updok") or
                                                      ($_REQUEST['pro'] == "updnok") ){
                                                echo "<th>#</th><th>Nome</th><th cass='center'>Versão</th><th class='center' >Versão Update</th><th>Pasta</th>";
                                            }else if (($_REQUEST['pro'] == "bolok") or
                                                      ($_REQUEST['pro'] == "bolnok") ){
                                                echo "<th>#</th><th>Nome</th><th cass='center'>Cidade</th><th class='center' >Valor Mensalidade</th><th>Plano</th>";
                                            }else if (($_REQUEST['pro'] == "cm113_A")   or
                                                      ($_REQUEST['pro'] == "cm113_On")  or
                                                      ($_REQUEST['pro'] == "cm113_Off") or
                                                      ($_REQUEST['pro'] == "cm113_Atz")){
                                                echo "<th>#</th><th>Nome</th><th cass='center'>Versão</th><th class='center' >Ult. Backup</th><th class='center' >OpenClose</th>";
                                            };
                                        };
 
                                    ?>
                                </tr>                                    
                            </thead>
                            <tbody>
                                <?php 
                                    include ('./Comandos/comandos.php');  
                                    include ('./Config/conexao.php');
                                    ini_set('default_charset', 'UTF-8');

                                    if (isset($_REQUEST['pro'])) {
                                        if ($_REQUEST['pro'] == "EmAndamento"){
                                            $sqlTable = mysqli_query($mysqli, $comandoSQL_ProtocoloEmAndamento); 
                                        }else if ($_REQUEST['pro'] == "Finalizado"){
                                            $sqlTable = mysqli_query($mysqli, $comandoSQL_ProtocoloFinalizados); 
                                        }else if ($_REQUEST['pro'] == "EmAberto"){
                                            $sqlTable = mysqli_query($mysqli, $comandoSQL_ProtocoloPendentes); 
                                        }else if ($_REQUEST['pro'] == "nfeon"){
                                            $sqlTable = mysqli_query($mysqli, $comandoSQL_ClientesNFeOnline); 
                                        }else if ($_REQUEST['pro'] == "nfeoff"){
                                            $sqlTable = mysqli_query($mysqli, $comandoSQL_ClientesNFeOffline); 
                                        }else if ($_REQUEST['pro'] == "nfceon"){
                                            $sqlTable = mysqli_query($mysqli, $comandoSQL_ClientesNFeOnline); 
                                        }else if ($_REQUEST['pro'] == "nfceoff"){
                                            $sqlTable = mysqli_query($mysqli, $comandoSQL_ClientesNFeOffline); 
                                        }else if ($_REQUEST['pro'] == "backupok"){
                                            $sqlTable = mysqli_query($mysqli, $comandoSQL_Backup_OK); 
                                        }else if ($_REQUEST['pro'] == "backupnok"){
                                            if ($_REQUEST['pri'] == "1"){ 
                                                $sqlTable = mysqli_query($mysqli, $comandoSQL_Backup_NOK_P1);
                                            }elseif ($_REQUEST['pri'] == "2"){ 
                                                $sqlTable = mysqli_query($mysqli, $comandoSQL_Backup_NOK_P2);
                                            }elseif($_REQUEST['pri'] == "3"){ 
                                                $sqlTable = mysqli_query($mysqli, $comandoSQL_Backup_NOK_P3);
                                            }elseif ($_REQUEST['pri'] == "4"){ 
                                                $sqlTable = mysqli_query($mysqli, $comandoSQL_Backup_NOK_P4);
                                            }elseif ($_REQUEST['pri'] == "5"){ 
                                                $sqlTable = mysqli_query($mysqli, $comandoSQL_Backup_NOK_P5);
                                            }else{
                                                $sqlTable = mysqli_query($mysqli, $comandoSQL_Backup_NOK);
                                            };
                                        }else if ($_REQUEST['pro'] == "finnok"){
                                            $sqlTable = mysqli_query($mysqli, $comando_Financeiro_Ativos); 
                                        }else if ($_REQUEST['pro'] == "finok"){
                                            $sqlTable = mysqli_query($mysqli, $comando_Financeiro_Bloqueados); 
                                        }else if ($_REQUEST['pro'] == "finlib"){
                                            $sqlTable = mysqli_query($mysqli, $comando_Financeiro_Liberados); 
                                        }else if ($_REQUEST['pro'] == "empok"){
                                            $sqlTable = mysqli_query($mysqli, $comando_Empresarial_Atualizados); 
                                        }else if ($_REQUEST['pro'] == "empnok"){
                                            $sqlTable = mysqli_query($mysqli, $comando_Empresarial_Desatualizados); 
                                        }else if ($_REQUEST['pro'] == "traok"){
                                            $sqlTable = mysqli_query($mysqli, $comando_Transportadora_Atualizados); 
                                        }else if ($_REQUEST['pro'] == "tranok"){
                                            $sqlTable = mysqli_query($mysqli, $comando_Transportadora_Desatualizados); 
                                        }else if ($_REQUEST['pro'] == "agrok"){
                                            $sqlTable = mysqli_query($mysqli, $comando_Agricola_Atualizados); 
                                        }else if ($_REQUEST['pro'] == "agrnok"){
                                            $sqlTable = mysqli_query($mysqli, $comando_Agricola_Desatualizados); 
                                        }else if ($_REQUEST['pro'] == "ftpok"){
                                            $sqlTable = mysqli_query($mysqli, $comando_CaloryFTP_Atualizado); 
                                        }else if ($_REQUEST['pro'] == "ftpnok"){
                                            $sqlTable = mysqli_query($mysqli, $comando_CaloryFTP_Desatualizado); 
                                        }else if ($_REQUEST['pro'] == "updok"){
                                            $sqlTable = mysqli_query($mysqli, $comando_CaloryUpdate_Atualizado); 
                                        }else if ($_REQUEST['pro'] == "updnok"){
                                            $sqlTable = mysqli_query($mysqli, $comando_CaloryUpdate_Desatualizado); 
                                        } else if($_REQUEST['pro'] == "bolok"){
                                            $sqlTable = mysqli_query($mysqli, $comando_GeraBoleto_SIM); 
                                        }else if($_REQUEST['pro'] == "bolnok"){
                                           $sqlTable = mysqli_query($mysqli, $comando_GeraBoleto_NAO); 
                                        }else if($_REQUEST['pro'] == "cm113_A"){
                                           $sqlTable = mysqli_query($mysqli, $Comando_CaloryManeger_Atualizados);                                            
                                        }else if($_REQUEST['pro'] == "cm113_On"){
                                           $sqlTable = mysqli_query($mysqli, $Comando_caloryManeger_ON);                                            
                                        }else if($_REQUEST['pro'] == "cm113_Off"){
                                           $sqlTable = mysqli_query($mysqli, $Comando_caloryManeger_OFF);                                            
                                        }else if($_REQUEST['pro'] == "cm113_Atz"){
                                           $sqlTable = mysqli_query($mysqli, $Comando_CaloryManeger_Atualizar);                                            
                                        };      
                                        };
                                    while ($linha = mysqli_fetch_array($sqlTable)) { 
                                        if ($_REQUEST['pro'] == "backupnok"){
                                         switch ($linha['prioridadeBackup']) {
                                                case 1:
                                                    echo "<tr class='danger'>";
                                                    break;
                                                case 2:
                                                    echo "<tr class='warning'>";
                                                    break;
                                                case 3:
                                                    echo "<tr class='success'>";
                                                    break;
                                                case 4:
                                                    echo "<tr class='info'>";
                                                    break;
                                                default:
                                                    echo "<tr>";
                                                    break;                                                    
                                            };   
                                        }else{
                                         echo "<tr class='odd gradeA' >";    
                                        }

                                ?>   
                                    
                                    <td><?php echo $linha['01']; ?></td>
                                    <td><?php echo $linha['02']; ?></td>
                                    <td><?php echo $linha['03']; ?></td>
                                    <td class="center"><?php echo $linha['04']; ?></td>
                                    <td class="center"><?php echo $linha['05']; ?></td>
                                    <?php
                                     if (($_REQUEST['pro'] == "finnok")or
                                         ($_REQUEST['pro'] == "finok") or 
                                         ($_REQUEST['pro'] == "finlib")){
                                        echo "<td class='center'>". $linha['06']."</td>";
                                        echo "<td class='center'>". $linha['07']."</td>";                               
                                     };                                     
                                    ?>
                                </tr>
                                    <?php }; ?>
                            </tbody>
                              
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>    
 <script src="assets/plugins/jquery-2.0.3.min.js"></script>
    <script src="assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="assets/plugins/modernizr-2.6.2-respond-1.1.0.min.js"></script>
    <!-- END GLOBAL SCRIPTS -->
        <!-- PAGE LEVEL SCRIPTS -->
    <script src="assets/plugins/dataTables/jquery.dataTables.js"></script>
    <script src="assets/plugins/dataTables/dataTables.bootstrap.js"></script>
     <script>
         $(document).ready(function () {
             $('#dataTables-example').dataTable();
         });
    </script>
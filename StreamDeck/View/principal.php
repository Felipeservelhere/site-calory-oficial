<?php
include('./Config/conexao.php');
include('./Comandos/comandos.php');
 if (isset($_REQUEST['page'])) {
    switch ($_REQUEST['page']) {
        case 'NFE1':
             header("Location: ./blank.php?dfe=NFE1");
            break;
        case 'NFEC1':
            header("Location: ./blank.php?dfe=NFCE1");
            break;
        case '3':
            $ComandosSQL_BackupAtraso = $ComandosSQL_BackupAtraso." and prioridadeBackup=3 ".$Orderby; 
            break;
        case '4':
            $ComandosSQL_BackupAtraso = $ComandosSQL_BackupAtraso." and prioridadeBackup=4 ".$Orderby; 
            break;
        default:
            $ComandosSQL_BackupAtraso = $ComandosSQL_BackupAtraso.$Orderby; 
            break;
    }
 }
?>﻿
<div class="inner" style="min-height: 1200px;">
    <div class="row">
        <div class="col-lg-12">
            <h1> Senha: <?php 
									
			
				$dia = date('d');
                $mes = date('m');
                $ano = date('Y');
                echo ($dia + $mes + $ano) * $mes; 		
				
				?>
            </h1>
            
        </div><!--Senha do DIA-->
    </div>                       
    <hr />
    <div class="row">
        <div class="col-lg-12">
            <div style="text-align: left;">
                <a class="quick-btn" href="Comandos/ExecutaFront.php">
                    <i class="icon-archive icon-2x"></i>
                    <span> MySqlFront</span>                                  
                </a>
                <a class="quick-btn" href="Comandos/ExecutaNotepad.php">
                    <i class="icon-archive icon-2x"></i>
                    <span> NotePad++</span>
                </a>
                <a class="quick-btn" href="Comandos/GeraProtocolo.php">
                    <i class="icon-cogs icon-2x"></i>
                    <span> Gerar RMA</span>                                  
                </a>
                <a class="quick-btn" href="https://docs.google.com/spreadsheets/d/10fOXIjntJFhhubx4S0V4MeX9knIS7S43qr_R2JmJzXM/edit?gid=0#gid=0"  target="_blank">
                    <i class=" icon-file-text-alt icon-2x"></i>
                    <span>Tarefas</span>                                  
                </a>
                <a class="quick-btn" href="https://web.whatsapp.com/"  target="_blank">
                    <i class=" icon-comments icon-2x"></i>
                    <span>Whatsapp</span>                                  
                </a>
            </div>
        </div><!--Menu Superior-->
    </div>
    <hr/>
    <div class="row">
                </div> <!--Calory MAnager Full-->
        <div class="col-lg-4">
            <div class="panel panel-success">
                <div class="panel-heading">                    
                    <i class="icon-building"></i>  
                    <?php 
                        $sql_113 = $mysqli-> query($comando_Versao_CaloryManager) or die($mysqli->error);
                        $linha113 = mysqli_fetch_array($sql_113); 
                        echo "Calory Manager - Versão: ".$linha113['lastversion'];      
                    ?>
                </div>
                <div class="row">
                    <div class="col-lg-12">
                        <div style="text-align: center;"> 
                            <?php
                                $sql = $mysqli->query($Comando_CaloryManeger_Atualizados) or die($mysqli->error);
                                
                                
                                $CaloryManager_Atualizados = mysqli_num_rows($sql);
                                $sql_Querycon = $mysqli->query($Comando_caloryManeger_ON) or die($mysqli->error);
                                $caloryManeger_ON = mysqli_num_rows($sql_Querycon);
                            ?>
                            <a class="quick-btn" href="index.php?page=rel&pro=cm113_A">
                                <i class=" icon-smile icon-2x"></i>
                                <span>Atualizados</span>
                                <span class="label label-Success"><?php echo $CaloryManager_Atualizados; ?></span>
                            </a>                                
                            <a class="quick-btn" href="index.php?page=rel&pro=cm113_On">
                                <i class=" icon-smile icon-2x"></i>
                                <span>Online</span>
                                <span class="label label-Success"><?php echo $caloryManeger_ON; ?></span>
                            </a>    
                            <?php
                                $sql_Querycoff = $mysqli->query($Comando_caloryManeger_OFF) or die($mysqli->error);
                                $caloryManeger_OFF = mysqli_num_rows($sql_Querycoff);
                                $sql = $mysqli->query($Comando_CaloryManeger_Atualizar) or die($mysqli->error);
                                $CaloryManeger_Desatualizados = mysqli_num_rows($sql);
                            ?>
                            <a class="quick-btn" href="index.php?page=rel&pro=cm113_Off">
                                <i class="icon-frown icon-2x"></i>
                                <span>Offline</span>
                                <span class="label label-Danger"><?php echo $caloryManeger_OFF;  ?></span>
                            </a>  
                             <a class="quick-btn" href="index.php?page=rel&pro=cm113_Atz">
                                <i class="icon-frown icon-2x"></i>
                                <span>Atualizar</span>
                                <span class="label label-Danger"><?php echo $CaloryManeger_Desatualizados;  ?></span>
                            </a>     
                        </div>
                    </div>
                </div>
            </div>
        </div> <!--Calory Manager-->
        <div class="col-lg-6">
            <div class="panel panel-danger">
                <div class="panel-heading">
                    <i class="icon-bell"></i> Notificações Protocolos (Backup,Financeiro)
                </div>
                <div class="row">
                    <div class="col-lg-12">
                        <div style="text-align: center;"> 
                            <?php
                                $sqlAndamento  = $mysqli->query($comandoSQL_ProtocoloEmAndamento) or die($mysqli->error);
                                $Protocolo_EmAndamento = mysqli_num_rows($sqlAndamento);
                            ?>
                            <a class="quick-btn" href="index.php?page=rel&pro=EmAndamento">
                                <i class="icon-cloud-upload icon-2x"></i>
                                <span>Fazendo</span>
                                <span class="label label-warning">
                                    <?php echo $Protocolo_EmAndamento; ?></span>                               
                            </a>    
                            <?php
                              $sqlFinalizado  = $mysqli->query($comandoSQL_ProtocoloFinalizados) or die($mysqli->error);                             
                              $Protocolo_Finalizado = mysqli_num_rows($sqlFinalizado);
                            ?>
                            <a class="quick-btn" href="index.php?page=rel&pro=Finalizado">
                                <i class="icon-check icon-2x"></i>
                                <span>Finalizado</span>
                                <span class="label label-success">
                                    <?php echo $Protocolo_Finalizado; ?>
                                </span>
                            </a>    
                            <?php
                              $sqlAberto  = $mysqli->query($comandoSQL_ProtocoloPendentes) or die($mysqli->error);
                              $Protocolo_Aberto = mysqli_num_rows($sqlAberto);
                            ?>
                            <a class="quick-btn" href="index.php?page=rel&pro=EmAberto">
                                <i class="icon-warning-sign icon-2x"></i>
                                <span>Aberto</span>
                                <span class="label label-danger">
                                  <?php echo $Protocolo_Aberto;  ?>
                                </span>
                            </a>   
                        </div>
                    </div>
                </div>
            </div>
        </div> <!--Notificações Protocolos (Backup,Financeiro)-->
        <div class="col-lg-6">
            <div class="panel panel-warning">
                <div class="panel-heading">
                    <i class="icon-money"></i> Notificações Inadiplentes
                </div>
                <div class="row">
                    <div class="col-lg-12">
                        <div style="text-align: center;"> 
                            <?php
                                $sql_Query8 = $mysqli->query($comando_Financeiro_Ativos) or die($mysqli->error);
                                $Inadiplentes_Ativos = mysqli_num_rows($sql_Query8);
                            ?>
                            <a class="quick-btn" href="index.php?page=rel&pro=finnok">
                                <i class="icon-cloud-upload icon-2x"></i>
                                <span>Ativos</span>
                                <span class="label label-danger"><?php echo $Inadiplentes_Ativos; ?></span>
                            </a>    
                            <?php
                                $sql_Query9 = $mysqli->query($comando_Financeiro_Bloqueados) or die($mysqli->error);
                                $Inadiplentes_Bloqueados = mysqli_num_rows($sql_Query9);
                            ?>
                            <a class="quick-btn" href="index.php?page=rel&pro=finok">
                                <i class="icon-cloud icon-2x"></i>
                                <span>Bloqueados</span>
                                <span class="label label-Success"><?php echo $Inadiplentes_Bloqueados; ?></span>
                            </a>    
                            <?php
                                $sql_Query16 = $mysqli->query($comando_Financeiro_Liberados) or die($mysqli->error);
                                $Inadiplentes_Liberados = mysqli_num_rows($sql_Query16);
                            ?>
                            <a class="quick-btn" href="index.php?page=rel&pro=finlib">
                                <i class="icon-cloud icon-2x"></i>
                                <span>Liberados</span>
                                <span class="label label-warning"><?php echo $Inadiplentes_Liberados;  ?></span>
                            </a>    
                        </div>
                    </div>
                </div>
            </div>
        </div> <!--Notificações Inadiplentes-->
                <div class="col-lg-4">
            <div class="panel panel-success">
                <div class="panel-heading">                    
                    <i class="icon-building"></i>  
                    <?php echo "Novo Empresarial - Versão: 895";  ?>
                </div>
                <div class="row">
                    <div class="col-lg-12">
                        <div style="text-align: center;"> 
                            <?php
                                $sql_Query_nea = $mysqli->query($comando_Novo_Empresaria_Atualizados) or die($mysqli->error);
                                $novoEmpresarial_Atualizados = mysqli_num_rows($sql_Query_nea);
                            ?>
                            <a class="quick-btn" href="index.php?page=rel&pro=empok">
                                <i class=" icon-smile icon-2x"></i>
                                <span>Atualizados</span>
                                <span class="label label-Success"><?php echo $novoEmpresarial_Atualizados; ?></span>
                            </a>    
                            <?php
                                $sql_Query_ned = $mysqli->query($comando_Novo_Empresaria_Desatualizados) or die($mysqli->error);
                                $novoEmpresarial_Desatualizados = mysqli_num_rows($sql_Query_ned);
                            ?>
                            <a class="quick-btn" href="index.php?page=rel&pro=empnok">
                                <i class="icon-frown icon-2x"></i>
                                <span>Atualizar</span>
                                <span class="label label-Danger"><?php echo $novoEmpresarial_Desatualizados;  ?></span>
                            </a>    
                        </div>
                    </div>
                </div>
            </div>
        </div> <!--Empresarial Full-->
        <div class="col-lg-4">
            <div class="panel panel-success">
                <div class="panel-heading">                    
                    <i class="icon-building"></i>  
                    <?php 
                        $sql_query17 = $mysqli-> query($comando_Versao_Empresarial) or die($mysqli->error);
                        $linha = mysqli_fetch_array($sql_query17); 
                        echo "Empresarial Lojas - Versão: ".$linha['lastversion'];      
                    ?>
                </div>
                <div class="row">
                    <div class="col-lg-12">
                        <div style="text-align: center;"> 
                            <?php
                                $sql_Query10 = $mysqli->query($comando_Empresarial_Atualizados) or die($mysqli->error);
                                $Empresarial_Atualizados = mysqli_num_rows($sql_Query10);
                            ?>
                            <a class="quick-btn" href="index.php?page=rel&pro=empok">
                                <i class=" icon-smile icon-2x"></i>
                                <span>Atualizados</span>
                                <span class="label label-Success"><?php echo $Empresarial_Atualizados; ?></span>
                            </a>    
                            <?php
                                $sql_Query11 = $mysqli->query($comando_Empresarial_Desatualizados) or die($mysqli->error);
                                $Empresarial_Desatualizados = mysqli_num_rows($sql_Query11);
                            ?>
                            <a class="quick-btn" href="index.php?page=rel&pro=empnok">
                                <i class="icon-frown icon-2x"></i>
                                <span>Atualizar</span>
                                <span class="label label-Danger"><?php echo $Empresarial_Desatualizados;  ?></span>
                            </a>    
                        </div>
                    </div>
                </div>
            </div>
        </div> <!--Empresarial-->
        <!--<div class="col-lg-4">
            <div class="panel panel-success">
                <div class="panel-heading">
                    <i class="icon-building"></i>
                    <?php 
                       /* $sql_query18 = $mysqli-> query($comando_Versao_Transportadoras) or die($mysqli->error);
                        $linha2 = mysqli_fetch_array($sql_query18); 
                        echo "Transportadoras Versão:".$linha2['lastversion'];      */
                    ?>
                </div>
                <div class="row">
                    <div class="col-lg-12">
                        <div style="text-align: center;"> 
                            <?php
                             /*   $sql_Query12 = $mysqli->query($comando_Transportadora_Atualizados) or die($mysqli->error);
                                $Transportadora_Atualizados = mysqli_num_rows($sql_Query12); */
                            ?>
                            <a class="quick-btn" href="index.php?page=rel&pro=traok">
                                <i class=" icon-smile icon-2x"></i>
                                <span>Atualizados</span>
                                <span class="label label-Success"><?php echo $Transportadora_Atualizados; ?></span>
                            </a>    
                            <?php
                               /* $sql_Query13 = $mysqli->query($comando_Transportadora_Desatualizados) or die($mysqli->error);
                                $Transportadora_Desatualizados = mysqli_num_rows($sql_Query13);*/
                            ?>
                            <a class="quick-btn" href="index.php?page=rel&pro=tranok">
                                <i class="icon-frown icon-2x"></i>
                                <span>Atualizar</span>
                                <span class="label label-Danger"><?php /*echo $Transportadora_Desatualizados; */?></span>
                            </a>    
                        </div>
                    </div>
                </div>
            </div>
        </div> --Transportadoras-->
        <div class="col-lg-4">
            <div class="panel panel-success">
                <div class="panel-heading">
                    <i class="icon-building"></i>
                    <?php 
                        $sql_query19 = $mysqli-> query($comando_Versao_Agricola) or die($mysqli->error);
                        $linha3 = mysqli_fetch_array($sql_query19); 
                        echo "Agricola Versão:".$linha3['lastversion'];      
                    ?>
                </div>
                <div class="row">
                    <div class="col-lg-12">
                        <div style="text-align: center;"> 
                            <?php
                                $sql_Query14 = $mysqli->query($comando_Agricola_Atualizados) or die($mysqli->error);
                                $Agricola_Atualizados = mysqli_num_rows($sql_Query14);
                            ?>
                            <a class="quick-btn" href="index.php?page=rel&pro=agrok">
                                <i class=" icon-smile icon-2x"></i>
                                <span>Atualizados</span>
                                <span class="label label-Success"><?php echo $Agricola_Atualizados; ?></span>
                            </a>    
                            <?php
                                $sql_Query15 = $mysqli->query($comando_Agricola_Desatualizados) or die($mysqli->error);
                                $Agricola_Desatualizados = mysqli_num_rows($sql_Query15);
                            ?>
                            <a class="quick-btn" href="index.php?page=rel&pro=agrnok">
                                <i class="icon-frown icon-2x"></i>
                                <span>Atualizar</span>
                                <span class="label label-Danger"><?php echo $Agricola_Desatualizados; ?></span>
                            </a>    
                        </div>
                    </div>
                </div>
            </div>
        </div> <!--Agricola-->
        <div class="col-lg-4">
            <div class="panel panel-success">
                <div class="panel-heading">                    
                    <i class="icon-building"></i>  
                    <?php 
                        $sql_query17 = $mysqli-> query($comando_Versao_EmpresarialNFe) or die($mysqli->error);
                        $linha = mysqli_fetch_array($sql_query17); 
                        echo "Emissor NFe Versão:".$linha['lastversion'];      
                    ?>
                </div>
                <div class="row">
                    <div class="col-lg-12">
                        <div style="text-align: center;"> 
                            <?php
                                $sqlNfeA = $mysqli->query($comando_EmissorNFE_Atualizados) or die($mysqli->error);
                                $EmpresarialNFe_Atualizados = mysqli_num_rows($sqlNfeA);
                            ?>
                            <a class="quick-btn" href="index.php?page=rel&pro=empok">
                                <i class=" icon-smile icon-2x"></i>
                                <span>Atualizados</span>
                                <span class="label label-Success"><?php echo $EmpresarialNFe_Atualizados;  ?></span>
                            </a>    
                            <?php
                                $sqlNfeD = $mysqli->query($comando_EmissorNFE_Desatualizados) or die($mysqli->error);
                                $EmpresarialNFe_Desatualizados = mysqli_num_rows($sqlNfeD);
                            ?>
                            <a class="quick-btn" href="index.php?page=rel&pro=empnok">
                                <i class="icon-frown icon-2x"></i>
                                <span>Atualizar</span>
                                <span class="label label-Danger"><?php echo $EmpresarialNFe_Desatualizados; ?></span>
                            </a>    
                        </div>
                    </div>
                </div>
            </div>
        </div> <!--EmpresarialNFe-->
        <div class="col-lg-4">
            <div class="panel panel-success">
                <div class="panel-heading">                    
                    <i class="icon-building"></i>  
                    <?php 
                        $sql_Update = $mysqli-> query($comando_Versao_CaloryUpdate) or die($mysqli->error);
                        $linha = mysqli_fetch_array($sql_Update); 
                        echo "CaloryUpdate Versão:".$linha['lastversion'];      
                    ?>
                </div>
                <div class="row">
                    <div class="col-lg-12">
                        <div style="text-align: center;"> 
                            <?php
                                $sqlUpdateA = $mysqli->query($comando_CaloryUpdate_Atualizado) or die($mysqli->error);
                                $EmpresarialNFCe_Atualizados = mysqli_num_rows($sqlUpdateA);
                            ?>
                            <a class="quick-btn" href="index.php?page=rel&pro=updok">
                                <i class=" icon-smile icon-2x"></i>
                                <span>Atualizados</span>
                                <span class="label label-Success"><?php echo $EmpresarialNFCe_Atualizados; ?></span>
                            </a>    
                            <?php
                               $sqlUpdateD = $mysqli->query($comando_CaloryUpdate_Desatualizado) or die($mysqli->error);
                               $EmpresarialNFCe_Desatualizados = mysqli_num_rows($sqlUpdateD);
                            ?>
                            <a class="quick-btn" href="index.php?page=rel&pro=updnok">
                                <i class="icon-frown icon-2x"></i>
                                <span>Atualizar</span>
                                <span class="label label-Danger"><?php echo $EmpresarialNFCe_Desatualizados; ?></span>
                            </a>    
                        </div>
                    </div>
                </div>
            </div>
        </div><!--CaloryUpdate-->
        <div class="col-lg-4">
            <div class="panel panel-success">
                <div class="panel-heading">                    
                    <i class="icon-building"></i>  
                    <?php 
                        $sql_FTP = $mysqli-> query($comando_Versao_CaloryFtp) or die($mysqli->error);
                        $linha = mysqli_fetch_array($sql_FTP); 
                        echo "CaloryFTP Versão:".$linha['lastversion'];      
                    ?>
                </div>
                <div class="row">
                    <div class="col-lg-12">
                        <div style="text-align: center;"> 
                            <?php
                                $sqlFtpA = $mysqli->query($comando_CaloryFTP_Atualizado) or die($mysqli->error);
                                $CaloryUpdate_Atualizados = mysqli_num_rows($sqlFtpA);
                            ?>
                            <a class="quick-btn" href="index.php?page=rel&pro=ftpok">
                                <i class=" icon-smile icon-2x"></i>
                                <span>Atualizados</span>
                                <span class="label label-Success"><?php echo $CaloryUpdate_Atualizados; ?></span>
                            </a>    
                            <?php
                               $sqlFtpD = $mysqli->query($comando_CaloryFTP_Desatualizado) or die($mysqli->error);
                               $CaloryUpdate_Desatualizados =  mysqli_num_rows($sqlFtpD);
                            ?>
                            <a class="quick-btn" href="index.php?page=rel&pro=ftpnok">
                                <i class="icon-frown icon-2x"></i>
                                <span>Atualizar</span>
                                <span class="label label-Danger"><?php echo $CaloryUpdate_Desatualizados;  ?></span>
                            </a>    
                        </div>
                    </div>
                </div>
            </div>
        </div> <!--CaloryFTP-->
        <div class="col-lg-4">
            <div class="panel panel-success">
                <div class="panel-heading">                    
                    <i class="icon-building"></i>  
                    <?php 
                        $sql_query17 = $mysqli-> query($comando_Versao_EmpresarialNFCe) or die($mysqli->error);
                        $linha = mysqli_fetch_array($sql_query17); 
                        echo "Emissor NFCe Versão:".$linha['lastversion'];      
                    ?>
                </div>
                <div class="row">
                    <div class="col-lg-12">
                        <div style="text-align: center;"> 
                            <?php
                                $sqlNFCeA = $mysqli->query($comando_EmissorNFCe_Atualizados) or die($mysqli->error);
                                $CaloryFTP_Atualizados = mysqli_num_rows($sqlNFCeA);
                            ?>
                            <a class="quick-btn" href="index.php?page=rel&pro=empok">
                                <i class=" icon-smile icon-2x"></i>
                                <span>Atualizados</span>
                                <span class="label label-Success"><?php echo $CaloryFTP_Atualizados;  ?></span>
                            </a>    
                            <?php
                                $sqlNFCeD = $mysqli->query($comando_EmissorNFCe_Desatualizados) or die($mysqli->error);
                                $CaloryFTP_Desatualizados = mysqli_num_rows($sqlNFCeD);
                            ?>
                            <a class="quick-btn" href="index.php?page=rel&pro=empnok">
                                <i class="icon-frown icon-2x"></i>
                                <span>Atualizar</span>
                                <span class="label label-Danger"><?php echo $CaloryFTP_Desatualizados; ?></span>
                            </a>    
                        </div>
                    </div>
                </div>
            </div>
        </div> <!--EmpresarialNFCe-->
        <div class="col-lg-4">
            <div class="panel panel-danger">
                <div class="panel-heading">
                    <i class="icon-bell"></i> Notificações Backup
                </div>
                <div class="row">
                    <div class="col-lg-12">
                        <div style="text-align: center;"> 
                            <?php
                                $sql_Query5 = $mysqli->query($comandoSQL_Backup_OK) or die($mysqli->error);
                                $Backup_EmDia = mysqli_num_rows($sql_Query5);
                            ?>
                            <a class="quick-btn" href="index.php?page=rel&pro=backupok">
                                <i class="icon-cloud-upload icon-2x"></i>
                                <span>Backup Ok</span>
                                <span class="label label-success"><?php echo $Backup_EmDia; ?></span>
                            </a>    
                            <?php
                                $sql_Query6 = $mysqli->query($comandoSQL_Backup_NOK) or die($mysqli->error);
                                $Backup_Atrasados = mysqli_num_rows($sql_Query6);
                            ?>
                            <a class="quick-btn" href="index.php?page=rel&pro=backupnok">
                                <i class="icon-cloud icon-2x"></i>
                                <span>Backup Nok</span>
                                <span class="label label-danger"><?php echo $Backup_Atrasados; ?></span>
                            </a>    
                        </div>
                    </div>
                </div>
            </div>
        </div><!--Notificações Backup-->
        <div class="col-lg-4">
            <div class="panel panel-Warning">
                <div class="panel-heading">
                    <i class="icon-bell"></i> Notificações Boletos
                </div>
                <div class="row">
                    <div class="col-lg-12">
                        <div style="text-align: center;"> 
                            <?php
                                $sql_Query7 = $mysqli->query($comando_GeraBoleto_SIM) or die($mysqli->error);
                                $GeraBoleto_SIM = mysqli_num_rows($sql_Query7);
                            ?>
                            <a class="quick-btn" href="index.php?page=rel&pro=bolok">
                                <i class="icon-usd icon-2x"></i>
                                <span>Gerados</span>
                                <span class="label label-success"><?php echo $GeraBoleto_SIM; ?></span>
                            </a>    
                            <?php
                                $sql_Query8 = $mysqli->query($comando_GeraBoleto_NAO) or die($mysqli->error);
                                $GeraBoleto_NAO = mysqli_num_rows($sql_Query8);
                            ?>
                            <a class="quick-btn" href="index.php?page=rel&pro=bolnok">
                                <i class="icon-usd icon-2x"></i>
                                <span>não Gerados</span>
                                <span class="label label-danger"><?php echo $GeraBoleto_NAO; ?></span>
                            </a>    
                        </div>
                    </div>
                </div>
            </div>
        </div><!--Notificações Gera Boleto-->
        <div class="col-lg-4">
            <div class="panel panel-success">
                <div class="panel-heading">
                    <i class="icon-bell"></i> Certificado no Banco
                </div>
                <div class="row">
                    <div class="col-lg-12">
                        <div style="text-align: center;"> 
                            <?php
                                $sql501 = $mysqli->query("SELECT LOGIN,qtdEmpresas FROM logcertificado where login <>'HOMOLOGACAO' ") or die($mysqli->error);
                                $CertificadoBD = mysqli_num_rows($sql501);
                            ?>
                            <a class="quick-btn" href="index.php?page=rel&pro=bolok">
                                <i class="icon-key icon-2x"></i>
                                <span>Log OK</span>
                                <span class="label label-success"><?php echo $CertificadoBD; ?></span>
                            </a>    
                               
                        </div>
                    </div>
                </div>
            </div>
        </div><!--Certificados no banco-->
    </div>
</div>
<?php
    $replaceDashBoard = " replace into logdashboard ( ".
                        " DATA,Tipo,Protocolo_EmAndamento,Protocolo_Finalizado,Protocolo_Aberto, ".
                        " Inadiplentes_Ativos,Inadiplentes_Bloqueados,Inadiplentes_Liberados, ".
                        " Empresarial_Atualizados,Empresarial_Desatualizados, ".
                        " Transportadora_Atualizados,Transportadora_Desatualizados, ".
                        " Agricola_Atualizados,Agricola_Desatualizados, ".
                        " EmpresarialNFe_Atualizados,EmpresarialNFe_Desatualizados, ".
                        " EmpresarialNFCe_Atualizados,EmpresarialNFCe_Desatualizados, ".
                        " CaloryUpdate_Atualizados,CaloryUpdate_Desatualizados, ".
                        " CaloryFTP_Atualizados,CaloryFTP_Desatualizados, ".
                        " Backup_EmDia,Backup_Atrasados)values ".
                        " (current_date(),(current_time() between '07:50:00' and '17:50:00'), ".
                        $Protocolo_EmAndamento.",".
                        $Protocolo_Finalizado.",".
                        $Protocolo_Aberto.",".
                        $Inadiplentes_Ativos.",".
                        $Inadiplentes_Bloqueados.",".
                        $Inadiplentes_Liberados.",".
                        $Empresarial_Atualizados.",".
                        $Empresarial_Desatualizados.",".
                        $Transportadora_Atualizados.",".
                        $Transportadora_Desatualizados.",".
                        $Agricola_Atualizados.",".
                        $Agricola_Desatualizados.",".
                        $EmpresarialNFe_Atualizados.",".
                        $EmpresarialNFe_Desatualizados.",".
                        $EmpresarialNFCe_Atualizados.",".
                        $EmpresarialNFCe_Desatualizados.",".
                        $CaloryUpdate_Atualizados.",".
                        $CaloryUpdate_Desatualizados.",".
                        $CaloryFTP_Atualizados.",".
                        $CaloryFTP_Desatualizados.",".
                        $Backup_EmDia.",".
                        $Backup_Atrasados.");";
    
        mysqli_query($mysqli,$replaceDashBoard);
?>

    
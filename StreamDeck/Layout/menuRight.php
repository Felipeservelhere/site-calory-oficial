<?php
include('../Config/conexao.php');
include('../Comandos/comandos.php');
?>
<div class="well well-small">
    <?php
            $ScriptDFE = "select count(*) qtd from cliente a ".
                         "join modulos b on b.idcliente=a.id ".
                         "where  ".
                         "(b.modulonfe=1  or b.modulonfce=1) ".
                         "AND plano not in (3,4) ".
                         "AND a.ativo not in ('C') ";

            $DataSetDfe = $mysqli->query($ScriptDFE) or die($mysqli->error);
            $ResultDFe  = $DataSetDfe->fetch_assoc();

            $ScriptDFEOn = " select count(*) qtd ".
                           " from logopenclosedfe ".
                           " where openclose=1 and data='" . date("Y-m-j") . "'";

            $DataSetDfeOn = $mysqli->query($ScriptDFEOn) or die($mysqli->error);
            $ResultDFeON  = $DataSetDfeOn->fetch_assoc();

            $porcOnline = round((($ResultDFeON['qtd']/$ResultDFe['qtd'])*100));

            $scriptAtualizado = " SELECT count(*) qtd ".
                                " FROM cliente a  ".
                                " JOIN modulos b ON b.idcliente=a.id  ".
                                " WHERE b.modulonfe=1  ".
                                " AND plano not in (3,4)  ".
                                " AND a.ativo not in ('C')  ".
                                " AND versaonfe=(select lastversion from produto where id=100) ".
                                " or  versaonfce=(select lastversion from produto where id=101) ";

            $DsAtualizado = $mysqli->query($scriptAtualizado) or die($mysqli->error);
            $ResultAtualizado  = $DsAtualizado->fetch_assoc();

            $porcAtualizado = round((($ResultAtualizado['qtd']/$ResultDFe['qtd'])*100));

            $porcOffline = round(((($ResultDFe['qtd']-$ResultDFeON['qtd'])/$ResultDFe['qtd'])*100));

            $porcDesatualizado = round(((($ResultDFe['qtd']-$ResultAtualizado['qtd'])/$ResultDFe['qtd'])*100));

            //DADOS CALORYFTP
               $script_Total_CaloryFTP = " select ".
                                         " COUNT(*) QTD ".
                                         " from cliente ".
                                         " where tipobackup=1 and ativo='S' ".
                                         " AND tipo_representante='0'";

            $DsTotal_CaloryFTP     = $mysqli->query($script_Total_CaloryFTP) or die($mysqli->error);
            $ResultTotalCaloryFTP  = $DsTotal_CaloryFTP->fetch_assoc();   

          $script_Atualizado_CaloryFTP = " SELECT ".
                                         " COUNT(*) QTD ".
                                         " FROM LOGATUALIZACAOCLIENTE ".
                                         " WHERE IDPRODUTO=107 ".
                                         " AND VERSAO=(SELECT LASTVERSION FROM PRODUTO WHERE ID=107)";

            $DsAtualizado_CaloryFTP = $mysqli->query($script_Atualizado_CaloryFTP) or die($mysqli->error);
            $ResultAtualizadoCaloryFTP  = $DsAtualizado_CaloryFTP->fetch_assoc();

            $porcAtualizadoCaloryFTP = round((($ResultAtualizadoCaloryFTP['QTD']/$ResultTotalCaloryFTP['QTD'])*100));

            $porcDesAtualizadoCaloryFTP = round(((($ResultTotalCaloryFTP['QTD']-$ResultAtualizadoCaloryFTP['QTD'])/$ResultTotalCaloryFTP['QTD'])*100));

    ?>
    <span>Dfe Online</span><span class="pull-right"><small><?php echo $porcOnline.'%';?></small></span>
    <div class="progress mini">
        <div class="progress-bar progress-bar-success" style="width:  <?php echo $porcOnline.'%';?>"></div>
    </div>
    <span>DFe Atualizado</span><span class="pull-right"><small><?php echo $porcAtualizado.'%';?></small></span>
    <div class="progress mini">
        <div class="progress-bar progress-bar-success" style="width: <?php echo $porcAtualizado.'%';?>"></div>
    </div>
    <span>DFe Offline</span><span class="pull-right"><small><?php echo $porcOffline.'%';?></small></span>
    <div class="progress mini">
        <div class="progress-bar progress-bar-warning" style="width: <?php echo $porcOffline.'%';?>"></div>
    </div>
    <span>DFE Desatualizado</span><span class="pull-right"><small><?php echo $porcDesatualizado.'%';?></small></span>
    <div class="progress mini">
        <div class="progress-bar progress-bar-danger" style="width: <?php echo $porcDesatualizado.'%';?>"></div>
    </div>
    <span>CaloryFTP Atualizado</span><span class="pull-right"><small><?php echo $porcAtualizadoCaloryFTP.'%';?></small></span>
    <div class="progress mini">
        <div class="progress-bar progress-bar-Success" style="width: <?php echo $porcAtualizadoCaloryFTP.'%';?>"></div>
    </div>
    <span>CaloryFTP Desatualizado</span><span class="pull-right"><small><?php echo $porcDesAtualizadoCaloryFTP.'%';?></small></span>
    <div class="progress mini">
        <div class="progress-bar progress-bar-danger" style="width: <?php echo $porcDesAtualizadoCaloryFTP.'%';?>"></div>
    </div>
</div>
         


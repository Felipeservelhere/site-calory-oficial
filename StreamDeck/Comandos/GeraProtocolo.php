<?php
include('./../Config/conexao.php');
/* 
 * Click nbfs://nbhost/SystemFileSystem/Templates/Licenses/license-default.txt to change this license
 * Click nbfs://nbhost/SystemFileSystem/Templates/Scripting/EmptyPHP.php to edit this template
 */
$cmdslq = " SELECT                                    ".
          " CASE                                      ".
          " WHEN idproduto=1  THEN 'Lojas'            ".
          " WHEN idproduto=2  THEN 'Transportadoras'  ".
          " WHEN idproduto=3  THEN 'FrutNorte'        ".
          " WHEN idproduto=4  THEN 'Fabrica'          ".
          " WHEN idproduto=5  THEN 'Agricola'         ".
          " WHEN idproduto=6  THEN 'Imobiliario'      ".
          " WHEN idproduto=7  THEN 'Veiculos'         ".
          " WHEN idproduto=8  THEN 'AgroNegocios'     ".
          " WHEN idproduto=9  THEN 'Insumos'          ".
          " WHEN idproduto=10 THEN 'Novo Imobiliaria' ".
          " ELSE 'Indefinido'                         ".
          " END Aplicacao,                            ".
          " id,nome,email,celular1,login,ultimo_bkp,prioridadeBackup,".
          " DATE_FORMAT(ultimo_bkp,'%d/%m/%Y  %H:%m') AS Ultimo, ".        
          " DATE_FORMAT(ultimo_bkp,'%d/%m/%Y  %H:%m') AS Ultimo, ".
          " DATEDIFF(COALESCE(dataopen,CURDATE()),ultimo_bkp) DiasAtraso ".
          " from cliente                              ".
          " where ativo='S'                           ".
          " and tipo_representante=0                  ".
          " and id_representante not in (411)         ".
          " and plano not in (3,4)                    ".
          " and localbd=0                             ".
          " and tipobackup=1                          ". 
          " and DATEDIFF(COALESCE(dataopen,CURDATE()),ultimo_bkp)>2 ".
          " AND ID not IN                                 ".
          " (select CODIGO_CLIENTE from solicitacao_cliente where tipo_servico='B' AND STATUS<>'F') ".
          " order by prioridadeBackup asc,diasatraso desc";

$cmdSqlFinanceiro = " SELECT id_representante,                  ".
                    " CASE                                      ".
                    " WHEN idproduto=1  THEN 'Lojas'            ".
                    " WHEN idproduto=2  THEN 'Transportadoras'  ".
                    " WHEN idproduto=3  THEN 'FrutNorte'        ".
                    " WHEN idproduto=4  THEN 'Fabrica'          ".
                    " WHEN idproduto=5  THEN 'Agricola'         ".
                    " WHEN idproduto=6  THEN 'Imobiliario'      ".
                    " WHEN idproduto=7  THEN 'Veiculos'         ".
                    " WHEN idproduto=8  THEN 'AgroNegocios'     ".
                    " WHEN idproduto=9  THEN 'Insumos'          ".
                    " WHEN idproduto=10 THEN 'Novo Imobiliaria' ".
                    " ELSE 'Indefinido'                         ".
                    " END Aplicacao,                            ".
                    " id,nome,email,celular1,versao,saldodevedor,".
                    "DATE_FORMAT(DATAPREVISTAACERTO,'%d/%m/%Y') AS DATAPREVISTAACERTO ".
                    " from cliente                              ".
                    " where ativo='S'                           ".
                    " AND saldodevedor3>0 ".
                    " AND BLOQUEAR='S' ".
                    " AND DATAPREVISTAACERTO<=CURDATE()".
                    " AND ID <>1411 AND ID not IN                                 ".
                    " (select CODIGO_CLIENTE from solicitacao_cliente ".
                    " where tipo_servico='F' AND STATUS<>'F') ".
                    " order by nome";  
        

$sqlFinalizaProtocoloFinanceiro = " SELECT ".
                                  " id,versao,dataprevistaacerto ".
                                  " FROM CLIENTE ".
                                  " WHERE ".
                                  " ativo='S' ".                           
                                  " AND saldodevedor3>0  ".
                                  " AND BLOQUEAR='S'  ".
                                  " AND DATAPREVISTAACERTO>CURDATE() ".
                                  " AND ID IN  ".
                                  " (select ".
                                  " CODIGO_CLIENTE ".
                                  " from solicitacao_cliente ".
                                  " where tipo_servico='F' AND STATUS='P')";

$sqlFinalizaProtocoloFinanceiroSaldoDedvedorZero = " SELECT                              ".
                                                   " id,versao,dataprevistaacerto        ".
                                                   " FROM CLIENTE                        ".
                                                   " WHERE                               ".
                                                   " ativo='S'                           ".
                                                   " AND saldodevedor3=0                 ".
                                                   " AND BLOQUEAR='S'                    ". 
                                                   " AND ID IN                           ".
                                                   " (select                             ".
                                                   " CODIGO_CLIENTE                      ".
                                                   " from solicitacao_cliente            ".  
                                                   " where tipo_servico='F' AND STATUS='P')";
 
$sqlCaloryManager_AplicacoesFechadas = " SELECT  ".
                                       " idmanager ".
                                       " FROM clientemanager ".
                                       " where PROXIMACOMUNICACAO < CURRENT_DATE and openclose=1";                                                   


try {
     
    if ($sql = mysqli_query($mysqli, $cmdslq)){
            
        while ($linha = mysqli_fetch_array($sql)) {
       
            $InsertSolicitacao = " INSERT INTO ".
                                 " solicitacao_cliente ".
                                 " (data_solicitacao,tipo_servico,".
                                 " codigo_cliente,nome_cliente,email_cliente,".
                                 " contato_cliente,msg_solicitacao,sistema_calory,".
                                 " status,idresponsavel,responsavel) VALUES ("."'".
                                 $linha['ultimo_bkp']."','B',".
                                 $linha['id'].",'".
                                 $linha['nome']."','".
                                 $linha['email']."','".
                                 $linha['celular1']."','"."Ultimo Backup: ".
                                 $linha['Ultimo']."\r\nData Abertura Aplicacao: ".
                                 $linha['dataopen']."\r\nPrioridade Nivel: ".                        
                                 $linha['prioridadeBackup']."\r\nDias em Atraso: ".
                                 $linha['DiasAtraso']."\r\n',"."'".
                                 $linha['Aplicacao']."','P',";

                                 IF ($linha['Aplicacao'] == 'Agricola') {
                                    $InsertSolicitacao = $InsertSolicitacao."916,'ELIAN');";
                                }else{    
                                    $InsertSolicitacao = $InsertSolicitacao."1160,'RENAN.PARRO');";
                                };
                         
              mysqli_query($mysqli, $InsertSolicitacao);                
            
           }         
    };
    
    if ($sql2 = mysqli_query($mysqli, $cmdSqlFinanceiro)){
       while ($linha2 = mysqli_fetch_array($sql2)) {
          $InsertSolicitacao2 =  " INSERT INTO ".
                                 " solicitacao_cliente ".
                                 " (data_solicitacao,tipo_servico,".
                                 "codigo_cliente,nome_cliente,email_cliente,".
                                 "contato_cliente,msg_solicitacao,sistema_calory,".
                                 "status,idresponsavel,responsavel ) VALUES ".
                                "(current_date".
                                 ",'F',".
                                 $linha2['id'].",'".
                                 $linha2['nome']."','".
                                 $linha2['email']."','".
                                 $linha2['celular1']."','".
                                 "Cliente Inadiplientes com sistema ativo\r\n".
                                 "Versao: ".$linha2['versao']."\r\n".   
                                 "Saldo Dededor: ".$linha2['saldodevedor']."\r\n".   
                                 "Data Acerto: ".$linha2['DATAPREVISTAACERTO']."\r\n',"."'".   
                                 $linha2['Aplicacao']."','P',";
          
                                 switch ($linha2['id_representante']) {
                                                case 411:
                                                    $InsertSolicitacao2 = $InsertSolicitacao2."411,'LAERCIO');";    
                                                    break;
                                                case 1160:
                                                    $InsertSolicitacao2 = $InsertSolicitacao2."1160,'RENAN.PARRO');";    
                                                    break;
                                                default:
                                                    $InsertSolicitacao2 = $InsertSolicitacao2."916,'ELIAN');";    
                                                    break;    
                                                
                                            };    
                      
          mysqli_query($mysqli, $InsertSolicitacao2);   
       }
      echo 'cabo';
    };
    
    if($sql4 = mysqli_query($mysqli, $sqlFinalizaProtocoloFinanceiro)){
        while ($linha4 = mysqli_fetch_array($sql4)) {
            $updateSolicitacaoFinaceiro = " update ".
                                          " solicitacao_cliente set ".
                                          " obs_responsavel='Encerrado Automatico pelo DashboardCS',".
                                          " data_finalizado=now(),".
                                          " status='F' ".
                                          " where tipo_servico='F' and codigo_cliente=".$linha4['id'].";";
  
          mysqli_query($mysqli, $updateSolicitacaoFinaceiro);   
       }
    };
    if($sql5 = mysqli_query($mysqli, $sqlFinalizaProtocoloFinanceiroSaldoDedvedorZero)){
        while ($linha5 = mysqli_fetch_array($sql5)) {
            $updateSolicitacaoFinaceiroSaldoZero = " update ".
                                                   " solicitacao_cliente set ".
                                                   " obs_responsavel='Encerrado Automatico pelo DashboardCS (Saldo Devedor = 0)',".
                                                   " data_finalizado=now(),".
                                                   " status='F' ".
                                                   " where tipo_servico='F' and codigo_cliente=".$linha5['id'].";";
   
          mysqli_query($mysqli, $updateSolicitacaoFinaceiroSaldoZero);   
       }
    };
    if($sql6 = mysqli_query($mysqli, $sqlCaloryManager_AplicacoesFechadas)){
        while ($linha6 = mysqli_fetch_array($sql6)) {
            $updateCaloryManager_AplicacoesFechadas = " update ".
                                                      " clientemanager set openclose=0 where PROXIMACOMUNICACAO < CURRENT_DATE";
   
          mysqli_query($mysqli, $updateCaloryManager_AplicacoesFechadas);   
       }
    };
    
} catch (Exception $e) {
           echo $e;
} finally {
    mysqli_close($mysqli);   
   
   header("Location: ../index.php");   
}


?>
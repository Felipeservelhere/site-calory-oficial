<script src="https://ajax.googleapis.com/ajax/libs/jquery/2.1.1/jquery.min.js"></script>
<?php
//exec('"C:\Program Files (x86)\MySQL-Front\MySQL-Front.exe"');

$ComandosSQL_BackupAtraso = " SELECT                                       ".
                            " CASE                                         ".
                            " WHEN idproduto=1  THEN 'Lojas'               ".
                            " WHEN idproduto=2  THEN 'Transportadoras'     ".
                            " WHEN idproduto=3  THEN 'FrutNorte'           ".
                            " WHEN idproduto=4  THEN 'Fabrica'             ".
                            " WHEN idproduto=5  THEN 'Agricola'            ".
                            " WHEN idproduto=6  THEN 'Imobiliario'         ".
                            " WHEN idproduto=7  THEN 'Veiculos'            ".
                            " WHEN idproduto=8  THEN 'AgroNegocios'        ".
                            " WHEN idproduto=9  THEN 'Insumos'             ".
                            " WHEN idproduto=10 THEN 'Novo Imobiliaria'    ".
                            " ELSE 'Indefinido'                            ".
                            " END Aplicacao,                               ".
                            " id,nome,login,ultimo_bkp,prioridadeBackup,   ".
                            " DATEDIFF(CURDATE(),ultimo_bkp) DiasAtraso    ".
                            " from cliente                                 ".
                            " where ativo='S'                              ".
                            " and tipoBackup=1                             ".
                            " and tipo_representante=0                     ".
                            " and plano not in (3,4)                       ".
                            " and DATEDIFF(CURDATE(),ULTIMO_BKP)>2         ";

                           

$comandoSql_BakupOK = " SELECT                                ".
                      " Count(*) qtd                          ".
                      " from cliente                          ".
                      " where ativo='S'                       ".
                      "and tipoBackup=1                       ".
                      " and tipo_representante=0              ".
                      " and plano not in (3,4)                ".
                      " and localbd=0                         ".
                      " and DATEDIFF(CURDATE(),ULTIMO_BKP)<=2 ";

$comandoSql_BakupNOK = " SELECT                              ".
                      " Count(*) qtd                         ".
                      " from cliente                         ".
                      " where ativo='S'                      ".
                      " and tipoBackup=1                      ".
                      " and tipo_representante=0             ".
                      " and plano not in (3,4)               ".
                      " and localbd=0                        ".
                      " and DATEDIFF(CURDATE(),ULTIMO_BKP)>2 ";
                      " and id_representante<>411            ";

$comandoSql_ProtocoloBackupEmANdamento = " SELECT                                ".
                                         " COUNT(*) qtd                          ".
                                         " FROM solicitacao_cliente              ".
                                         " WHERE STATUS='A' AND TIPO_SERVICO='B' "; 

$comandoSql_ProtocoloBackupFinalizado = " SELECT ".
                                        " COUNT(*) qtd ".
                                        " FROM solicitacao_cliente ".
                                        " WHERE STATUS='F' AND TIPO_SERVICO='B'".
                                        " AND DATE(DATA_FINALIZADO)=CURRENT_DATE ";

$comandoSql_ProtocoloBackupPendente = " SELECT ".
                                      " COUNT(*) qtd ".
                                      " FROM solicitacao_cliente ".
                                      " WHERE STATUS='P' AND TIPO_SERVICO='B'";

$comandoSql_EmissorNFeVersaoOld   = " SELECT ".
                                     " a.id '01',a.nome '02',a.versao '03',".
                                     "a.versaonfe '04',date_Format(a.ultimo_bkp,'%d/%m/%Y') '05'".
                                     " FROM cliente a  ".
                                     " JOIN modulos b ON b.idcliente=a.id  ".
                                     " WHERE b.modulonfe=1  ".
                                     "and a.tipo_representante='0' ".                                      
                                     " AND plano not in (3,4)  ".
                                     " AND a.ativo='S'  ".
                                     " AND versaonfe<>(select lastversion from produto where id=100) ".
                                     " order by versaonfe ";

$comandoSql_EmissorNFCeVersaoOld   = " SELECT ".
                                     " a.id '01',a.nome '02',a.versao '03',".
                                     "a.versaonfe '04',date_Format(a.ultimo_bkp,'%d/%m/%Y') '05'".
                                     " FROM cliente a  ".
                                     " JOIN modulos b ON b.idcliente=a.id  ".
                                     " WHERE b.modulonfce=1  ".
                                     "and a.tipo_representante='0' ".                                      
                                     " AND plano not in (3,4)  ".
                                     " AND a.ativo='S'  ".
                                     " AND versaonfce<>(select lastversion from produto where id=101) ".
                                     " order by versaonfe ";

$comandoSQL_ProtocoloFechados     = " select ".
                                    " RESPONSAVEL,date(data_finalizado) data,COUNT(*) qtd ".
                                    " from solicitacao_cliente ".
                                    " where STATUS='F' AND TIPO_SERVICO='B' ".
                                    " GROUP BY RESPONSAVEL,date(data_finalizado) ".
                                    " order by date(data_finalizado) desc,qtd desc ";

$comandoSQL_BackupAtrasadoLaercio = " SELECT                                   ".
                                    " id '01',nome '02',versao '03',           ".
                                    " date_Format(ultimo_bkp,'%d/%m/%Y') '04', ".
                                    " DATEDIFF(CURDATE(),ultimo_bkp) '05'      ".    
                                    " from cliente                             ".
                                    " where ativo='S'                          ".
                                    " and tipoBackup=1                         ".
                                    " and tipo_representante=0                 ".
                                    " and plano not in (3,4)                   ".
                                    " and localbd=0                            ".
                                    " and DATEDIFF(CURDATE(),ULTIMO_BKP)>2     ".
                                    " and id_representante=411                 ".
                                    " order by DATEDIFF(CURDATE(),ultimo_bkp) desc";

$comandoSQL_QtdBackupAtrasadoLaercio = " SELECT                               ".
                                       " count(*) qtd                         ".
                                       " from cliente                         ".
                                       " where ativo='S'                      ".
                                       " and tipoBackup=1                     ".
                                       " and tipo_representante=0             ".
                                       " and plano not in (3,4)               ".
                                       " and localbd=0                        ".
                                       " and DATEDIFF(CURDATE(),ULTIMO_BKP)>2 ".
                                       " and id_representante=411             ";
                                       



$comandoSql_SelectBackupNOK =       " SELECT                               ".
                                    " ID '01',NOME '02',VERSAO '03',       ".
                                    " CIDADE '04',                         ".
                                    " date_Format(ULTIMO_BKP,'%d/%m/%Y %h:%m') '05' ".
                                    " from cliente                         ".
                                    " where ativo='S'                      ".
                                    " and tipoBackup=1                     ".
                                    " and tipo_representante=0             ".
                                    " and plano not in (3,4)               ".
                                    " and localbd=0                        ".
                                    " and DATEDIFF(CURDATE(),ULTIMO_BKP)>2 ";
                                    " and id_representante<>411            ".
                                    " order by ULTIMO_BKP desc";

                                
$comandoSQL_ClientesNFe = " SELECT ".
                          " a.id '01' ".
                          ",a.nome '02'".
                          ",a.versao '03' ".
                          ",a.versaonfe '04'".
                          ",date_Format(a.ULTIMO_BKP,'%d/%m/%Y')'05' ".
                          " FROM cliente a  ".
                          " JOIN modulos b ON b.idcliente=a.id  ".
                          " WHERE b.modulonfe=1  ".
                          " and a.tipo_representante='0' ".
                          " AND plano not in (3,4)  ".
                          " AND a.ativo='S'  ";

$comandoSQL_ClientesNFeOnline = $comandoSQL_ClientesNFe.
                                " and a.cnpj in (select cnpj from logopenclosedfe where tipo=0 and openclose=1 ".  
                                " and data='" . date("Y-m-j") . "')";

$comandoSQL_ClientesNFeOffline = $comandoSQL_ClientesNFe.
                                 " and a.cnpj not in (select cnpj from logopenclosedfe where tipo=0 and openclose=1 ".  
                                 " and data='" . date("Y-m-j") . "')";

$comandoSQL_ClientesNFCe = " SELECT ".
                          " a.id '01' ".
                          ",a.nome '02'".
                          ",a.versao '03' ".
                          ",a.versaonfe '04'".
                          ",date_Format(a.ULTIMO_BKP,'%d/%m/%Y')'05' ".
                          " FROM cliente a  ".
                          " JOIN modulos b ON b.idcliente=a.id  ".
                          " WHERE b.modulonfce=1  ".
                          " and a.tipo_representante='0' ".
                          " AND plano not in (3,4)  ".
                          " AND a.ativo='S'  ";

$comandoSQL_ClientesNFCeOnline = $comandoSQL_ClientesNFe.
                                " and a.cnpj in (select cnpj from logopenclosedfe where tipo=1 and openclose=1 ".  
                                " and data='" . date("Y-m-j") . "')";

$comandoSQL_ClientesNFCeOffline = $comandoSQL_ClientesNFe.
                                 " and a.cnpj not in (select cnpj from logopenclosedfe where tipo=1 and openclose=1 ".  
                                 " and data='" . date("Y-m-j") . "')";   

$comandoSQL_Backup = " SELECT id '01', ".
                     " CASE ".
                     " WHEN idproduto=1  THEN 'Lojas' ".
                     " WHEN idproduto=2  THEN 'Transportadoras' ".
                     " WHEN idproduto=3  THEN 'FrutNorte' ".
                     " WHEN idproduto=4  THEN 'Fabrica' ".
                     " WHEN idproduto=5  THEN 'Agricola' ".
                     " WHEN idproduto=6  THEN 'Imobiliario' ".
                     " WHEN idproduto=7  THEN 'Veiculos' ".
                     " WHEN idproduto=8  THEN 'AgroNegocios' ".
                     " WHEN idproduto=9  THEN 'Insumos' ".
                     " WHEN idproduto=10 THEN 'Novo Imobiliaria' ".
                     " ELSE 'Indefinido' ".
                     " END '02', ".
                     " nome '03',date_Format(ultimo_bkp,'%d/%m/%Y') '04', ".
                     " prioridadeBackup, ".
                     " DATEDIFF(COALESCE(dataopen,CURDATE()),ultimo_bkp) '05' ".
                     " from cliente ".
                     " where ativo='S' ".
                     " and tipoBackup=1 ".
                     " and tipo_representante=0 ".
                     " and plano not in (3,4)";

$comandoSQL_Backup_OK  = $comandoSQL_Backup." and DATEDIFF(COALESCE(dataopen,CURDATE()),ultimo_bkp) <=2";
$comandoSQL_Backup_NOK = $comandoSQL_Backup." and DATEDIFF(COALESCE(dataopen,CURDATE()),ultimo_bkp) >2";
$comandoSQL_Backup_NOK_P1 = $comandoSQL_Backup_NOK." and prioridadeBackup=1";
$comandoSQL_Backup_NOK_P2 = $comandoSQL_Backup_NOK." and prioridadeBackup=2";
$comandoSQL_Backup_NOK_P3 = $comandoSQL_Backup_NOK." and prioridadeBackup=3";
$comandoSQL_Backup_NOK_P4 = $comandoSQL_Backup_NOK." and prioridadeBackup=4";
$comandoSQL_Backup_NOK_P5 = $comandoSQL_Backup_NOK." and localbd=3";


$comando_Financeiro = " select ".
                      " id '01',nome '02',CIDADE '03',".
                      " bloquear '04',saldodevedor3 '05',date_Format(DATAPREVISTAACERTO,'%d/%m/%Y') '06',".
                      " versao '07' ".        
                      " from cliente where saldodevedor3>0 ".
                      " AND DATAPREVISTAACERTO<=CURDATE()";

$comando_Financeiro_Ativos = $comando_Financeiro." AND BLOQUEAR='S' and ativo='S'";
        
$comando_Financeiro_Bloqueados = $comando_Financeiro." AND BLOQUEAR='S' and ativo='N' "; 

$comando_Financeiro_Liberados =  $comando_Financeiro." AND BLOQUEAR='N'  and ativo='S' "; 

$comando_Empresarial = " select ".
                       " a.id '01' ".
                       " ,a.nome '02' ".
                       " ,A.versao '03' ".
                       " ,a.cidade '04' ".
                       " ,date_Format(ULTIMO_BKP,'%d/%m/%Y')'05' ".
                       " from cliente A ".
                       " JOIN PRODUTO B ON B.ID=A.IDPRODUTO ".
                       " where a.ativo='S' ".
                       " and a.plano not in (3) ".
                       " and a.tipobackup=1 ";

$comando_Novo_Empresaria_Atualizados    = $comando_Empresarial." and a.idproduto in (1,2) and a.versao>= '895' ";
$comando_Novo_Empresaria_Desatualizados = $comando_Empresarial." and a.idproduto in (1,2) and a.versao<= '895' ";


$comando_Empresarial_Atualizados = $comando_Empresarial.
                                   " and a.idproduto=1 and a.versao >= b.lastversion ";

$comando_Empresarial_Desatualizados = $comando_Empresarial.
                                      " and a.idproduto=1 and a.versao < b.lastversion";

$comando_Transportadora_Atualizados = $comando_Empresarial.
                                      " and a.idproduto=2 and a.versao >= b.lastversion ";

$comando_Transportadora_Desatualizados = $comando_Empresarial.
                                         " and a.idproduto=2 and a.versao < b.lastversion";

$comando_Agricola_Atualizados = $comando_Empresarial.
                                      " and a.idproduto=5 and a.versao >= b.lastversion ";

$comando_Agricola_Desatualizados = $comando_Empresarial.
                                         " and a.idproduto=5 and a.versao < b.lastversion";

$comando_EmissorNFE = " select                                  ".
                      " a.id '01'                               ".
                      "  ,a.nome '02'                           ".     
                      " ,A.versaonfe '03'                       ".
                      " ,a.cidade '04'                          ".
                      " ,date_Format(ULTIMO_BKP,'%d/%m/%Y')'05' ". 
                      " from cliente A                          ".
                      " join modulos b on b.idcliente=a.id      ".
                      " where a.ativo='S'                       ".
                      " and a.plano not in (3)                  ".
                      " and b.modulonfe=1                       ".
                      " and a.tipobackup=1                      ";

$comando_EmissorNFE_Atualizados    = $comando_EmissorNFE." and replace(a.versaonfe,'.','')>=(select replace(lastversion,'.','') lastversion from produto where id=100)";
$comando_EmissorNFE_Desatualizados = $comando_EmissorNFE." and replace(a.versaonfe,'.','')<(select replace(lastversion,'.','') lastversion from produto where id=100)";

$comando_EmissorNFCE = " select                                  ".
                       " a.id '01'                               ".
                       "  ,a.nome '02'                           ".     
                       " ,A.versaonfce '03'                       ".
                       " ,a.cidade '04'                          ".
                       " ,date_Format(ULTIMO_BKP,'%d/%m/%Y')'05' ". 
                       " from cliente A                          ".
                       " join modulos b on b.idcliente=a.id      ".        
                       " where a.ativo='S'                       ".
                       " and a.plano not in (3)                  ".
                       " and b.modulonfce=1                       ".
                       " and a.tipobackup=1                      ";

$comando_EmissorNFCe_Atualizados    = $comando_EmissorNFCE." and replace(a.versaonfce,'.','')>=(select replace(lastversion,'.','') from produto where id=101)";
$comando_EmissorNFCe_Desatualizados = $comando_EmissorNFCE." and replace(a.versaonfce,'.','')<(select replace(lastversion,'.','') from produto where id=101)";


$comando_Versao = "select lastversion from produto where id=";
$comando_Versao_Empresarial     = $comando_Versao."1";
$comando_Versao_Transportadoras = $comando_Versao."2";
$comando_Versao_Agricola        = $comando_Versao."5";
$comando_Versao_CaloryUpdate    = $comando_Versao."98";
$comando_Versao_EmpresarialNFe  = $comando_Versao."100";
$comando_Versao_EmpresarialNFCe = $comando_Versao."101";
$comando_Versao_CaloryFtp       = $comando_Versao."107";
$comando_Versao_CaloryBalanca   = $comando_Versao."108";
$comando_Versao_CaloryManager   = $comando_Versao."113";



$comandoSQL_Protocolo            = " select nprotocolo '01',concat(codigo_cliente,' - ',nome_cliente) '02', ".
                                   " CASE                                      ".
                                   " WHEN tipo_servico='B'  THEN 'Backup'      ".      
                                   " WHEN tipo_servico='O'  THEN 'Outros'      ".
                                   " WHEN tipo_servico='F'  THEN 'Financeciro' ".       
                                   " ELSE 'Indefinido'                         ".
                                   " END '03',                                 ".
                                   " Responsavel '04',                         ".
                                   " date_Format(data_solicitacao,'%d/%m/%Y %h:%m') '05'".
                                   " from solicitacao_cliente                  ";

$comandoSQL_ProtocoloPendentes    = $comandoSQL_Protocolo." where status='P' order by data_solicitacao";

$comandoSQL_ProtocoloFinalizados  = $comandoSQL_Protocolo." where status='F' and date(data_finalizado)=current_date() order by data_solicitacao";

$comandoSQL_ProtocoloEmAndamento  = $comandoSQL_Protocolo." where status='A' order by data_solicitacao";

$comando_logAtualizacaoCliente = " select ".
                                 " C.ID '01',C.NOME '02',C.VERSAO '03',A.VERSAO '04', ".
                                 " c.login '05' ".
                                 " from logatualizacaocliente A ".
                                 " JOIN PRODUTO B ON B.ID=A.IDPRODUTO ".
                                 " JOIN CLIENTE C ON C.ID=A.IDCLIENTE".
                                 " where c.ativo='S' ".
                                 " and c.plano not in (3) ".
                                 " and c.tipobackup=1 ";

$comando_CaloryUpdate_Atualizado    = $comando_logAtualizacaoCliente.
                                      " and A.IDPRODUTO=98 AND A.VERSAO>=B.lastversion ";

$comando_CaloryUpdate_Desatualizado = $comando_logAtualizacaoCliente.
                                      " and A.IDPRODUTO=98 AND A.VERSAO<B.lastversion ";

$comando_CaloryFTP_Atualizado    = $comando_logAtualizacaoCliente.
                                   " and A.IDPRODUTO=107 AND A.VERSAO>=B.lastversion ";

$comando_CaloryFTP_Desatualizado = $comando_logAtualizacaoCliente.
                                      " and A.IDPRODUTO=107 AND A.VERSAO<B.lastversion ";

$comando_GeraBoleto = " select ".
                      " id '01',nome '02',".
                      " concat(cidade,' - ',uf) '03',valor_mensalidade '04', ".                    
                      " case ". 
                      " when plano=0 then 'Anual' ".
                      " when plano=1 then 'Mensalista' ".
                      " when plano=2 then 'Paga e Usa' ".
                      " END '05' ".
                      " from cliente ".
                      " where tipo_representante=0 ".
                      " and plano not in (3,4) ".
                      " and ativo<>'C' ";

$comando_GeraBoleto_SIM = $comando_GeraBoleto." and boletogerado='S' ";
$comando_GeraBoleto_NAO = $comando_GeraBoleto." and boletogerado='N' ";        
                                    
$Comando_CaloryManeger = " select ".             
                         " a.idmanager '01', ".
                         " a.nome '02', ".
                         " a.versao '03', ".
                         " DATE_FORMAT(b.ultimo_bkp, '%d/%m/%Y %H:%i') AS '04', ".
                         " a.openclose '05' ".
                         " from clientemanager a ".
                         " join cliente b on b.id=a.idmanager ".
                         " where a.ativo=1";

$Comando_caloryManeger_ON  = $Comando_CaloryManeger." and a.openclose=1";
$Comando_caloryManeger_OFF = $Comando_CaloryManeger." and a.openclose=0";

$Comando_CaloryManeger_Atualizados  = $Comando_CaloryManeger." and a.versao >=(".$comando_Versao_CaloryManager.")";
$Comando_CaloryManeger_Atualizar    = $Comando_CaloryManeger." and a.versao < (".$comando_Versao_CaloryManager.")";
                          
?>

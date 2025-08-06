<!DOCTYPE html>
<!--[if IE 8]> <html lang="pt-br" class="ie8"> <![endif]-->
<!--[if IE 9]> <html lang="pt-br" class="ie9"> <![endif]-->
<!--[if !IE]><!--> <html lang="pt-br"> <!--<![endif]-->

<!-- BEGIN HEAD-->
<head>
   
     <meta charset="UTF-8" />
    <title>Dashboard CS</title>
     <meta content="width=device-width, initial-scale=1.0" name="viewport" />
	<meta content="" name="description" />
	<meta content="" name="author" />
     <!--[if IE]>
        <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
        <![endif]-->
    <!-- GLOBAL STYLES -->
    <!-- GLOBAL STYLES -->
    <link rel="stylesheet" href="assets/plugins/bootstrap/css/bootstrap.css" />
    <link rel="stylesheet" href="assets/css/main.css" />
    <link rel="stylesheet" href="assets/css/theme.css" />
    <link rel="stylesheet" href="assets/css/MoneAdmin.css" />
    <link rel="stylesheet" href="assets/plugins/Font-Awesome/css/font-awesome.css" />
    <!--END GLOBAL STYLES -->
<link href="assets/plugins/dataTables/dataTables.bootstrap.css" rel="stylesheet" />
    <!-- PAGE LEVEL STYLES -->
    <!-- END PAGE LEVEL  STYLES -->
       <!-- HTML5 shim and Respond.js IE8 support of HTML5 elements and media queries -->
    <!--[if lt IE 9]>
      <script src="https://oss.maxcdn.com/libs/html5shiv/3.7.0/html5shiv.js"></script>
      <script src="https://oss.maxcdn.com/libs/respond.js/1.3.0/respond.min.js"></script>
    <![endif]-->
</head>
    <!-- END  HEAD-->
    <!-- BEGIN BODY-->
<body class="padTop53 " >

     <!-- MAIN WRAPPER -->
    <div id="wrap">


         <!-- HEADER SECTION -->
        <div id="top">
            <nav class="navbar navbar-inverse navbar-fixed-top " style="padding-top: 10px;">
                <a data-original-title="Show/Hide Menu" data-placement="bottom" data-tooltip="tooltip" class="accordion-toggle btn btn-primary btn-sm visible-xs" data-toggle="collapse" href="#menu" id="menu-toggle">
                    <i class="icon-align-justify"></i>
                </a>              
                <header class="navbar-header">

                    <a href="index.html" class="navbar-brand">
                    <img src="assets/img/logo.png" alt="" /></a>
                </header>
            </nav>
        </div>
        <!-- END HEADER SECTION -->



        <!-- MENU SECTION -->
       <div id="left">
            <div class="media user-media well-small">
                <a class="user-link" href="#">
                    <img class="media-object img-thumbnail user-img" alt="User Picture" src="assets/img/user.gif" />
                </a>
                <br />
                <div class="media-body">
                    <h5 class="media-heading"> Regimar Souza</h5>
                    <ul class="list-unstyled user-info">
                        
                        <li>
                             <a class="btn btn-success btn-xs btn-circle" style="width: 10px;height: 12px;"></a> Online
                           
                        </li>
                       
                    </ul>
                </div>
                <br />
            </div>

            <ul id="menu" class="collapse">                
                <li class="panel">
                    <a href="index.php" >
                        <i class="icon-home"></i> Home                        
                    </a>                   
                </li>
            </ul>

        </div>
        <!--END MENU SECTION -->


        <!--PAGE CONTENT -->
        <div id="content">

            <div class="inner" style="min-height:1200px;">
                <div class="row">
                    <div class="col-lg-12">
                        <?php   
                            if (isset($_REQUEST['dfe'])) {
                                switch ($_REQUEST['dfe']) {
                                    case 'NFE1':
                                         echo "<h2>Clientes com Emissor (NFe/CTe/MDFe) Desatualizados</h2>";
                                        break;
                                    case 'NFCE1':
                                        echo "<h2>Clientes com Emissor NFCe Desatualizados</h2>";
                                        break;
                                    case 'parc1':
                                        echo "<h2>Clientes backup atrasados (Laercio)</h2>"; 
                                        break;
                                    case 'pro01':
                                        echo "<h2>Protocolos em Andamento</h2>"; 
                                        break;
                                    case 'pro02':
                                        echo "<h2>Protocolos Finalizados</h2>"; 
                                        break;
                                    case 'bkp02':
                                        echo "<h2>Backup em Atrasos</h2>"; 
                                        break;
                                  
                                }
                                
                            }
                            if (isset($_REQUEST['ver'])) {
                              echo "<h2>Clientes na Versão ".$_REQUEST['ver']." </h2>"; 
                                   
                             }
                        ?>
                    </div>
                </div>
                <hr />
<!--INICIO DO CODIGO TABELAS REGIMAR-->
                <div class="row">
                <div class="col-lg-12">
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            DataTables Advanced Tables
                        </div>
                        <div class="panel-body">
                            <div class="table-responsive">
                                 
                                <table class="table table-striped table-bordered table-hover" id="dataTables-example">
                                    <thead>
                                        <tr>
                                            <?php
                                                if (isset($_REQUEST['dfe'])) {
                                                        switch ($_REQUEST['dfe']) {
                                                            case 'NFE1':
                                                                echo "<th>ID</th>".
                                                                     "<th>Nome</th>".
                                                                     "<th>Versão</th>".
                                                                     "<th>Versão NFe</th>".
                                                                     "<th>Último Backup</th>";                                                      
                                                                break;
                                                            case 'NFCE1':
                                                                echo "<th>ID</th>".
                                                                     "<th>Nome</th>".
                                                                     "<th>Versão</th>".
                                                                     "<th>Versão NFe</th>".
                                                                     "<th>Último Backup</th>";                                                      
                                                                break;
                                                            case 'parc1':
                                                                echo "<th>ID</th>".
                                                                     "<th>Nome</th>".
                                                                     "<th>Versão</th>".
                                                                     "<th>Último Backup</th>".
                                                                     "<th>Dias Atraso</th>";                                                      
                                                                break;
                                                            case 'pro01':
                                                                echo "<th>Protocolo</th>".
                                                                     "<th>Nome</th>".
                                                                     "<th>Tipo Serviço</th>".
                                                                     "<th>Responsável</th>".
                                                                     "<th>Data Abertura</th>";                                                      
                                                                break;
                                                            case 'pro02':
                                                                echo "<th>Protocolo</th>".
                                                                     "<th>Nome</th>".
                                                                     "<th>Tipo Serviço</th>".
                                                                     "<th>Responsável</th>".
                                                                     "<th>Data Finalizado</th>";                                                      
                                                                break;
                                                             case 'pro03':
                                                                echo "<th>Protocolo</th>".
                                                                     "<th>Nome</th>".
                                                                     "<th>Tipo Serviço</th>".
                                                                     "<th>Sistema</th>".
                                                                     "<th>Data Solicitação</th>";                                                      
                                                                break;
                                                            case 'bkp02':
                                                                echo "<th>ID</th>".
                                                                     "<th>Nome</th>".
                                                                     "<th>Versão</th>".
                                                                     "<th>Cidade</th>".
                                                                     "<th>Último Backup</th>";                                                      
                                                                break;
                                                            

                                                        }
                                                    }
                                                     if (isset($_REQUEST['ver'])) {
                                                        echo "<th>ID</th>".
                                                             "<th>Nome</th>".
                                                             "<th>Versão</th>".
                                                             "<th>Cidade</th>".
                                                             "<th>Último Backup</th>";   
                                   
                             }
                                            ?>
                                            
                                            
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                          include ('./Comandos/comandos.php');  
                                          include ('./Config/conexao.php');
                                          ini_set('default_charset', 'UTF-8');
                                          if (isset($_REQUEST['dfe'])) {
                                                        switch ($_REQUEST['dfe']) {
                                                            case 'NFE1':
                                                                $sqlTable = mysqli_query($mysqli, $comandoSql_EmissorNFeVersaoOld);
                                                                break;
                                                            case 'NFCE1':
                                                                $sqlTable = mysqli_query($mysqli, $comandoSql_EmissorNFCeVersaoOld);
                                                                break;
                                                            case 'parc1':
                                                                $sqlTable = mysqli_query($mysqli, $comandoSQL_BackupAtrasadoLaercio);                                                      
                                                                break;
                                                            case 'pro01':
                                                                $sqlTable = mysqli_query($mysqli, $comandoSQL_ProtocolosEmAndamento);                                                      
                                                                break;
                                                            case 'pro02':
                                                                $sqlTable = mysqli_query($mysqli, $comandoSQL_ProtocolosFinalizado);                                                      
                                                                break;
                                                            case 'pro03':
                                                                $sqlTable = mysqli_query($mysqli, $comandoSQL_ProtocolosEmAndamento);                                                      
                                                                break;
                                                             case 'bkp02':
                                                                $sqlTable = mysqli_query($mysqli, $comandoSql_SelectBackupNOK);                                                      
                                                                break;

                                                        }
                                                    }
                                                     if (isset($_REQUEST['ver'])) {
                                                         $comandoSQL_BuscaPorVersao = $comandoSQL_BuscaPorVersao." and a.versao='".$_REQUEST['ver']."' ";
                                                         $sqlTable = mysqli_query($mysqli, $comandoSQL_BuscaPorVersao);
                                                         
                                                    }
                                                    
                                          
                                          
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





<!--FIM DO CODIGO TABELAS-->


                


            </div>




        </div>
       <!--END PAGE CONTENT -->


    </div>

     <!--END MAIN WRAPPER -->

   <!-- FOOTER -->
    <div id="footer">
        <p>&copy;  binarytheme &nbsp;2014 &nbsp;</p>
    </div>
    <!--END FOOTER -->
     <!-- GLOBAL SCRIPTS -->
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
    <!-- END GLOBAL SCRIPTS -->
</body>
    <!-- END BODY-->
    
</html>

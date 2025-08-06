<?php
    if (isset($_REQUEST['page'])) {
        switch ($_REQUEST['page']) {
            case 'rel':
                include 'view/relatorio.php';                
                break;
            case 'bkp':
                include 'paginas/sensores.php';
                break;
            case 'iluminacao':
                include 'paginas/iluminacao.php';
                break;
            case 'conexao':
                include 'paginas/conexao.php';
                break;
            default:
                include ("paginas/home.php");
                break;
        }
    } else {
         include 'View/principal.php';
    }


?>
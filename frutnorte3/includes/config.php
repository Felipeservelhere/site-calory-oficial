<?php
// config.php


// Define a URL base do sistema
function url($path = '') {
    // Remove barras duplicadas
    $path = ltrim($path, '/');
    
    // Se estiver usando subdiretório, ajuste aqui
    $base_url = '/sistema'; // Altere para o caminho base do seu sistema
    
    return $base_url . '/' . $path;
}

// Definição das páginas para menu ativo
$current_page = basename($_SERVER['PHP_SELF']);

// Grupos de páginas para menu ativo
$clientesPages = ['clientes.php', 'cadastro-clientes.php', 'editar_cliente.php'];
$produtosPages = ['produtos.php', 'cadastro-produtos.php', 'editar_produto.php', 'grupos.php'];
$estoquePages = ['estoque.php', 'entradas.php', 'inventario.php'];
$vendasPages = ['vendas.php', 'nova-venda.php', 'historico-vendas.php'];
$financasPages = ['tipopagamentos.php', 'condicoesfaturamentos.php', 'tipodespesas.php', 'centrocusto.php'];
$contasPages = ['contaspagar.php', 'contas-receber.php', 'relatorio-contas.php'];

// Informações do usuário logado
$usuario_logado = 'Usuário';
$empresa_logada = 'Empresa';
$usuario_email = '';

// Capturar informações do usuário logado via DatabaseLogin
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true && isset($_SESSION['admin_id'])) {
    try {
        // Inclui o arquivo de conexão
        require_once __DIR__ . '/../config/databaselogin.php';
        
        $dbLogin = new DatabaseLogin();
        $connlogin = $dbLogin->getConnection();
        
        if ($connlogin) {
            $admin_id = $_SESSION['admin_id'];
            
            // Query para buscar informações do usuário e empresa
            $stmt = $connlogin->prepare("
                SELECT 
                    u.nome as usuario_nome,
                    u.email as usuario_email,
                    e.nome_empresa as empresa_nome
                FROM usuarios u 
                LEFT JOIN empresas e ON u.empresa_id = e.id 
                WHERE u.id = ? AND u.status = 1
            ");
            $stmt->execute([$admin_id]);
            $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user_data) {
                $usuario_logado = $user_data['usuario_nome'] ?? 'Usuário';
                $usuario_email = $user_data['usuario_email'] ?? '';
                $empresa_logada = $user_data['empresa_razao_social'] ?? $user_data['empresa_nome'] ?? 'Empresa';
                
                // Salva na sessão para evitar múltiplas consultas
                $_SESSION['usuario_nome'] = $usuario_logado;
                $_SESSION['usuario_email'] = $usuario_email;
                $_SESSION['empresa_nome'] = $empresa_logada;
            }
        }
    } catch (Exception $e) {
        // Log do erro (opcional)
        error_log("Erro ao buscar dados do usuário: " . $e->getMessage());
        // Mantém os valores padrão em caso de erro
    }
}

// Funções auxiliares para o avatar
function getIniciais($nome) {
    $nomes = explode(' ', $nome);
    $iniciais = '';
    
    if (count($nomes) >= 2) {
        $iniciais = strtoupper(substr($nomes[0], 0, 1) . substr($nomes[count($nomes)-1], 0, 1));
    } else {
        $iniciais = strtoupper(substr($nome, 0, 2));
    }
    
    return $iniciais;
}

function getCorAvatar($nome) {
    $cores = [
        '#10b981', '#3b82f6', '#f59e0b', '#ef4444', '#8b5cf6',
        '#06b6d4', '#84cc16', '#f97316', '#ec4899', '#6366f1'
    ];
    
    $hash = crc32($nome);
    $index = abs($hash) % count($cores);
    
    return $cores[$index];
}
?>
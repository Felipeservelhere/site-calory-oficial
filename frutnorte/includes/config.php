<?php
// includes/config.php - Configurações globais do sistema
// Estrutura assumida:
// - Raiz/
//   - includes/
//     - config.php (este arquivo)
//     - menu.php
//   - config/  (pasta separada na raiz para banco de dados)
//     - databaselogin.php
//     - database.php

// Inicia a sessão se não estiver iniciada (fallback, mas inicie no topo das páginas principais como index.php)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Define a URL base do sistema (para links absolutos, resolvendo problemas de subpastas)
function url($path = '') {
    // Remove '/' inicial do path para evitar duplicação
    $path = ltrim($path, '/');
    
    // Base URL: '/' para raiz do domínio (ex: https://calory.com.br/entradas.php)
    // Se o site estiver em subpasta (ex: calory.com.br/meusistema/), descomente e ajuste:
    // $base_url = '/meusistema';
    $base_url = '/';
    
    // Garante que não haja barras duplicadas
    return rtrim($base_url, '/') . '/' . $path;
}

// Definição da página atual (para menu ativo - usa basename para ignorar pastas)
if (isset($_SERVER['PHP_SELF'])) {
    $current_page = basename($_SERVER['PHP_SELF']);
} else {
    $current_page = 'index.php';  // Default se não detectado (raro)
}

// Grupos de páginas para menu ativo (use basename para consistência com subpastas como acoes_clientes/)
$clientesPages = ['clientes.php', 'cadastro-clientes.php', 'editar_cliente.php'];
$produtosPages = ['produtos.php', 'cadastro-produtos.php', 'editar_produto.php', 'grupos.php'];
$estoquePages = ['estoque.php', 'entradas.php', 'inventario.php'];
$vendasPages = ['vendas.php', 'nova-venda.php', 'historico-vendas.php'];
$financasPages = ['tipopagamentos.php', 'condicoesfaturamentos.php', 'tipodespesas.php', 'centrocusto.php'];
$contasPages = ['contaspagar.php', 'contas-receber.php', 'relatorio-contas.php'];

// Informações do usuário logado (com caminho corrigido para databaselogin.php)
$usuario_logado = 'Usuário';
$empresa_logada = 'Empresa';
$usuario_email = '';

if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true && isset($_SESSION['admin_id'])) {
    try {
        // Caminho corrigido: de includes/ para raiz/config/ (sobe um nível com '../', depois entra em config/)
        // Se databaselogin.php estiver em outro lugar, ajuste aqui (ex: '../../databaselogin.php' se na raiz)
        require_once '../config/databaselogin.php';
        
        $dbLogin = new DatabaseLogin();
        $connlogin = $dbLogin->getConnection();
        
        if ($connlogin) {
            $admin_id = $_SESSION['admin_id'];
            
            // Query corrigida: usa 'nome_empresa' consistentemente
            // Ajuste o nome da coluna/join se o seu banco usar 'razao_social' ou outro campo
            $stmt = $connlogin->prepare("
                SELECT 
                    u.nome as usuario_nome,
                    u.email as usuario_email,
                    e.nome_empresa as empresa_nome  -- Mude para 'razao_social' se for o campo real no banco
                FROM usuarios u 
                LEFT JOIN empresas e ON u.empresa_id = e.id 
                WHERE u.id = ? AND u.status = 1
            ");
            $stmt->execute([$admin_id]);
            $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user_data) {
                $usuario_logado = $user_data['usuario_nome'] ?? 'Usuário';
                $usuario_email = $user_data['usuario_email'] ?? '';
                $empresa_logada = $user_data['empresa_nome'] ?? 'Empresa';  // Consistente com o alias da query
                
                // Salva na sessão para evitar múltiplas consultas em outras páginas
                $_SESSION['usuario_nome'] = $usuario_logado;
                $_SESSION['usuario_email'] = $usuario_email;
                $_SESSION['empresa_nome'] = $empresa_logada;
            }
        }
    } catch (Exception $e) {
        // Log do erro sem quebrar o script (verifique error_log no servidor)
        error_log("Erro ao buscar dados do usuário em includes/config.php: " . $e->getMessage());
        // Mantém valores padrão em caso de erro (ex: conexão falhou)
    }
} else {
    // Se não logado, valores padrão (não redireciona aqui para evitar loops em páginas públicas)
    // Se quiser forçar login, descomente: header("Location: ../login.php"); exit;
}

// Funções auxiliares para avatar no menu (mantidas como estavam - úteis para exibir iniciais)
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
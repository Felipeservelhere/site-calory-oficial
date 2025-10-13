<?php
// Menu lateral corporativo para dashboard
include 'config.php';
?>

<!-- FAVICON -->
<link rel="icon" type="image/x-icon" href="<?php echo url('assets/favicon.ico'); ?>">
<link rel="icon" type="image/png" sizes="32x32" href="<?php echo url('assets/favicon-32x32.png'); ?>">
<link rel="icon" type="image/png" sizes="16x16" href="<?php echo url('assets/favicon-16x16.png'); ?>">
<link rel="apple-touch-icon" sizes="180x180" href="<?php echo url('assets/apple-touch-icon.png'); ?>">
<link rel="manifest" href="<?php echo url('assets/site.webmanifest'); ?>">

<style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        background: linear-gradient(135deg, #f6f8fb 0%, #e9eef7 100%);
        min-height: 100vh;
        color: #1e293b;
    }

    /* Sidebar */
    .sidebar {
        position: fixed;
        left: 0;
        top: 0;
        width: 280px;
        height: 100vh;
        background: #ffffff;
        border-right: 1px solid #e2e8f0;
        z-index: 1000;
        box-shadow: 0 0 20px rgba(0, 0, 0, 0.08);
        display: flex;
        flex-direction: column;
        transform: translateX(-100%);
        transition: transform 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .sidebar.active {
        transform: translateX(0);
    }

    /* Header mais compacto e elegante */
    .sidebar-header {
        padding: 24px 20px 20px;
        background: linear-gradient(135deg, #3B82F6 0%, #2563EB 100%);
        border-bottom: 1px solid rgba(255,255,255,0.1);
    }

    .logo-img {
        width: 160px;
        height: auto;
        margin-bottom: 16px;
        margin-left: 60px;
        filter: brightness(0) invert(1);
    }

    /* User info redesenhada */
    .user-info-section {
        display: flex;
        align-items: center;
        padding: 16px;
        background: rgba(255,255,255,0.15);
        backdrop-filter: blur(10px);
        border-radius: 10px;
        margin: 0;
        border: 1px solid rgba(255,255,255,0.2);
    }

    .user-avatar {
        margin-right: 12px;
        flex-shrink: 0;
    }

    .avatar-circle {
        width: 44px;
        height: 44px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #3B82F6;
        background: white;
        font-weight: 700;
        font-size: 15px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        border: 2px solid rgba(255,255,255,0.3);
    }

    .user-details {
        flex: 1;
        min-width: 0;
    }

    .user-name {
        font-size: 15px;
        font-weight: 600;
        color: white;
        margin: 0 0 2px 0;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .user-company {
        font-size: 12px;
        color: rgba(255,255,255,0.85);
        margin: 0 0 2px 0;
        font-weight: 500;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .user-email {
        font-size: 11px;
        color: rgba(255,255,255,0.7);
        margin: 0;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .user-status {
        margin-left: 8px;
    }

    .status-indicator {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: #10b981;
        position: relative;
        box-shadow: 0 0 8px rgba(16, 185, 129, 0.5);
    }

    .status-indicator.online::after {
        content: '';
        position: absolute;
        top: -3px;
        left: -3px;
        right: -3px;
        bottom: -3px;
        border-radius: 50%;
        background: #10b981;
        opacity: 0.4;
        animation: pulse 2s infinite;
    }

    @keyframes pulse {
        0%, 100% {
            transform: scale(1);
            opacity: 0.4;
        }
        50% {
            transform: scale(1.5);
            opacity: 0.2;
        }
    }

    /* Menu section com scrollbar customizada */
    .menu-section {
        flex: 1;
        padding: 16px 0 24px 0;
        overflow-y: auto;
        scrollbar-width: thin;
        scrollbar-color: #cbd5e1 #f8fafc;
    }

    .menu-section::-webkit-scrollbar {
        width: 6px;
    }

    .menu-section::-webkit-scrollbar-track {
        background: #f8fafc;
    }

    .menu-section::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 10px;
    }

    .menu-section::-webkit-scrollbar-thumb:hover {
        background: #94a3b8;
    }

    .menu-group {
        margin-bottom: 24px;
    }

    .menu-group-title {
        padding: 0 24px 10px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: #94a3b8;
    }

    .menu-item {
        display: flex;
        align-items: center;
        padding: 13px 24px;
        color: #475569;
        text-decoration: none;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
        margin: 0 12px;
        border-radius: 10px;
        font-weight: 500;
        font-size: 14px;
        cursor: pointer;
    }

    .menu-item:hover {
        background: #f1f5f9;
        color: #0f172a;
        transform: translateX(3px);
    }

    /* COR AZUL no item ativo */
    .menu-item.active {
        background: linear-gradient(135deg, #3B82F6 0%, #2563EB 100%);
        color: white;
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        font-weight: 600;
    }

    .menu-item.active::before {
        content: '';
        position: absolute;
        left: -12px;
        top: 50%;
        transform: translateY(-50%);
        width: 4px;
        height: 28px;
        background: #3B82F6;
        border-radius: 0 4px 4px 0;
    }

    .menu-item i {
        margin-right: 12px;
        font-size: 16px;
        width: 20px;
        text-align: center;
        opacity: 0.85;
    }

    .menu-item.active i {
        opacity: 1;
    }

    /* Submenu melhorado */
    .submenu {
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        background: #f8fafc;
        border-radius: 10px;
        margin: 4px 12px;
    }

    .submenu.active {
        max-height: 400px;
    }

    .submenu-item {
        display: flex;
        align-items: center;
        padding: 11px 20px 11px 48px;
        color: #64748b;
        text-decoration: none;
        transition: all 0.2s ease;
        font-size: 13px;
        font-weight: 500;
        border-left: 2px solid transparent;
        position: relative;
    }

    .submenu-item:hover {
        background: #f1f5f9;
        color: #3B82F6;
        border-left-color: #3B82F6;
    }

    .submenu-item.active {
        background: linear-gradient(90deg, rgba(59,130,246,0.1) 0%, transparent 100%);
        color: #3B82F6;
        border-left-color: #3B82F6;
        font-weight: 600;
    }

    .submenu-item i {
        font-size: 11px;
        margin-right: 10px;
        opacity: 0.7;
    }

    .menu-item.has-submenu::after {
        content: '\f078';
        font-family: 'Font Awesome 6 Free';
        font-weight: 900;
        position: absolute;
        right: 20px;
        font-size: 11px;
        transition: transform 0.3s ease;
        color: currentColor;
        opacity: 0.6;
    }

    .menu-item.has-submenu.active::after {
        transform: rotate(180deg);
    }

    /* Botão hambúrguer moderno */
    .menu-toggle {
        position: fixed;
        top: 20px;
        left: 20px;
        width: 48px;
        height: 48px;
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        cursor: pointer;
        z-index: 1001;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .menu-toggle:hover {
        background: #3B82F6;
        border-color: #3B82F6;
        box-shadow: 0 8px 25px rgba(59, 130, 246, 0.25);
        transform: translateY(-2px);
    }

    .menu-toggle:hover i {
        color: white;
    }

    .menu-toggle.active {
        background: #3B82F6;
        border-color: #3B82F6;
    }

    .menu-toggle i {
        font-size: 18px;
        color: #475569;
        transition: all 0.2s ease;
    }

    .menu-toggle.active i {
        color: white;
        transform: rotate(90deg);
    }

    /* Conteúdo principal */
    .main-content {
        margin-left: 0;
        min-height: 100vh;
        background: linear-gradient(135deg, #f6f8fb 0%, #e9eef7 100%);
        transition: all 0.3s ease;
        padding-left: 88px;
    }

    .content-wrapper {
        padding: 32px;
        max-width: 1400px;
    }

    .page-header {
        background: white;
        border-radius: 14px;
        padding: 32px;
        margin-bottom: 24px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    }

    .page-title {
        font-size: 28px;
        font-weight: 700;
        color: #0f172a;
        margin-bottom: 8px;
        letter-spacing: -0.025em;
    }

    .page-subtitle {
        color: #64748b;
        font-size: 16px;
        font-weight: 400;
        line-height: 1.6;
    }

    /* Cards de estatísticas com azul */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 24px;
        margin-top: 32px;
    }

    .stat-card {
        background: white;
        border-radius: 14px;
        padding: 24px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        transition: all 0.2s ease;
        position: relative;
        overflow: hidden;
    }

    .stat-card:hover {
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        transform: translateY(-2px);
    }

    .stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(135deg, #3B82F6 0%, #2563EB 100%);
    }

    .stat-card.secondary::before {
        background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
    }

    .stat-card.tertiary::before {
        background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    }

    .stat-card.quaternary::before {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    }

    .stat-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 16px;
    }

    .stat-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        color: white;
        background: linear-gradient(135deg, #3B82F6 0%, #2563EB 100%);
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
    }

    .stat-card.secondary .stat-icon {
        background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
        box-shadow: 0 4px 12px rgba(139, 92, 246, 0.3);
    }

    .stat-card.tertiary .stat-icon {
        background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
    }

    .stat-card.quaternary .stat-icon {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
    }

    .stat-title {
        font-size: 13px;
        font-weight: 600;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-bottom: 8px;
    }

    .stat-value {
        font-size: 28px;
        font-weight: 700;
        color: #0f172a;
        margin-bottom: 4px;
    }

    .stat-change {
        font-size: 12px;
        font-weight: 500;
        color: #10b981;
    }

    .stat-change.negative {
        color: #ef4444;
    }

    /* Overlay */
    .overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(15, 23, 42, 0.6);
        opacity: 0;
        visibility: hidden;
        transition: all 0.25s ease;
        z-index: 999;
        backdrop-filter: blur(4px);
    }

    .overlay.active {
        opacity: 1;
        visibility: visible;
    }

    .main-content.menu-open {
        filter: blur(2px);
        pointer-events: none;
    }

    /* Animações */
    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .stat-card {
        animation: slideIn 0.5s ease forwards;
    }

    .stat-card:nth-child(1) { animation-delay: 0.1s; opacity: 0; }
    .stat-card:nth-child(2) { animation-delay: 0.2s; opacity: 0; }
    .stat-card:nth-child(3) { animation-delay: 0.3s; opacity: 0; }
    .stat-card:nth-child(4) { animation-delay: 0.4s; opacity: 0; }

    /* Responsividade */
    @media (max-width: 768px) {
        .main-content {
            padding-left: 72px;
        }
        
        .content-wrapper {
            padding: 20px;
        }
        
        .page-header {
            padding: 24px;
        }
        
        .stats-grid {
            grid-template-columns: 1fr;
        }
        
        .menu-toggle {
            width: 44px;
            height: 44px;
        }
        
        .menu-toggle i {
            font-size: 16px;
        }
    }

    @media (max-width: 640px) {
        .sidebar {
            width: 100%;
        }
        
        .main-content {
            padding-left: 68px;
        }
        
        .content-wrapper {
            padding: 16px;
        }
        
        .page-header {
            padding: 20px;
        }
        
        .page-title {
            font-size: 24px;
        }
        
        .stat-card {
            padding: 20px;
        }
        
        .menu-toggle {
            width: 42px;
            height: 42px;
            top: 16px;
            left: 16px;
        }
    }
</style>

<!-- Google Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

<!-- Font Awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<!-- Botão Toggle -->
<button class="menu-toggle" id="menuToggle">
    <i class="fas fa-bars"></i>
</button>

<!-- Overlay -->
<div class="overlay" id="overlay"></div>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <!-- Logo opcional -->
        <img src="<?php echo url('images/logo.png'); ?>" alt="Logo" class="logo-img">
        
        <!-- User info -->
        <div class="user-info-section">
            <div class="user-avatar">
                <?php 
                $iniciais = getIniciais($usuario_logado);
                ?>
                <div class="avatar-circle">
                    <?php echo $iniciais; ?>
                </div>
            </div>
            <div class="user-details">
                <h3 class="user-name"><?php echo htmlspecialchars($usuario_logado); ?></h3>
                <p class="user-company"><?php echo htmlspecialchars($empresa_logada); ?></p>
                <?php if (!empty($usuario_email)): ?>
                    <p class="user-email"><?php echo htmlspecialchars($usuario_email); ?></p>
                <?php endif; ?>
            </div>
            <div class="user-status">
                <div class="status-indicator online" title="Online"></div>
            </div>
        </div>
    </div>
    
    <div class="menu-section">
        <div class="menu-group">
            <div class="menu-group-title">Principal</div>
            <a href="<?php echo url('index.php'); ?>" class="menu-item <?php echo $current_page == 'index.php' ? 'active' : ''; ?>">
                <i class="fas fa-chart-line"></i>
                <span>Dashboard</span>
            </a>
        </div>
        
        <div class="menu-group">
            <div class="menu-group-title">Gestão</div>
            
            <!-- Clientes -->
            <div class="menu-item has-submenu <?php echo in_array($current_page, $clientesPages) ? 'active' : ''; ?>" id="clientesMenu">
                <i class="fas fa-users"></i>
                <span>Clientes</span>
            </div>
            <div class="submenu <?php echo in_array($current_page, $clientesPages) ? 'active' : ''; ?>" id="clientesSubmenu">
                <a href="<?php echo url('clientes.php'); ?>" class="submenu-item <?php echo $current_page == 'clientes.php' ? 'active' : ''; ?>">
                    <i class="fas fa-list"></i>
                    <span>Lista de Clientes</span>
                </a>
                <a href="<?php echo url('acoes_clientes/cadastro-clientes.php'); ?>" class="submenu-item <?php echo $current_page == 'cadastro-clientes.php' ? 'active' : ''; ?>">
                    <i class="fas fa-plus"></i>
                    <span>Cadastrar Cliente</span>
                </a>
                <a href="<?php echo url('acoes_clientes/editar_cliente.php'); ?>" class="submenu-item <?php echo $current_page == 'editar_cliente.php' ? 'active' : ''; ?>">
                    <i class="fas fa-edit"></i>
                    <span>Editar Cliente</span>
                </a>
            </div>
            
            <!-- Produtos -->
            <div class="menu-item has-submenu <?php echo in_array($current_page, $produtosPages) ? 'active' : ''; ?>" id="produtosMenu">
                <i class="fas fa-box"></i>
                <span>Produtos</span>
            </div>
            <div class="submenu <?php echo in_array($current_page, $produtosPages) ? 'active' : ''; ?>" id="produtosSubmenu">
                <a href="<?php echo url('produtos.php'); ?>" class="submenu-item <?php echo $current_page == 'produtos.php' ? 'active' : ''; ?>">
                    <i class="fas fa-list"></i>
                    <span>Lista de Produtos</span>
                </a>
                <a href="<?php echo url('acoes_produtos/cadastro-produtos.php'); ?>" class="submenu-item <?php echo $current_page == 'cadastro-produtos.php' ? 'active' : ''; ?>">
                    <i class="fas fa-plus"></i>
                    <span>Cadastrar Produto</span>
                </a>
                <a href="<?php echo url('acoes_produtos/editar_produto.php'); ?>" class="submenu-item <?php echo $current_page == 'editar_produto.php' ? 'active' : ''; ?>">
                    <i class="fas fa-edit"></i>
                    <span>Editar Produto</span>
                </a>
                <a href="<?php echo url('grupos.php'); ?>" class="submenu-item <?php echo $current_page == 'grupos.php' ? 'active' : ''; ?>">
                    <i class="fas fa-tags"></i>
                    <span>Grupos</span>
                </a>
            </div>
            
            <!-- Estoque -->
            <div class="menu-item has-submenu <?php echo in_array($current_page, $estoquePages) ? 'active' : ''; ?>" id="estoqueMenu">
                <i class="fas fa-warehouse"></i>
                <span>Estoque</span>
            </div>
            <div class="submenu <?php echo in_array($current_page, $estoquePages) ? 'active' : ''; ?>" id="estoqueSubmenu">
                <a href="<?php echo url('estoque.php'); ?>" class="submenu-item <?php echo $current_page == 'estoque.php' ? 'active' : ''; ?>">
                    <i class="fas fa-boxes"></i>
                    <span>Controle de Estoque</span>
                </a>
                <a href="<?php echo url('entradas.php'); ?>" class="submenu-item <?php echo $current_page == 'entradas.php' ? 'active' : ''; ?>">
                    <i class="fas fa-exchange-alt"></i>
                    <span>Entrada de produtos</span>
                </a>
                <a href="<?php echo url('inventario.php'); ?>" class="submenu-item <?php echo $current_page == 'inventario.php' ? 'active' : ''; ?>">
                    <i class="fas fa-clipboard-check"></i>
                    <span>Inventário</span>
                </a>
            </div>
            
            <!-- Vendas -->
            <div class="menu-item has-submenu <?php echo in_array($current_page, $vendasPages) ? 'active' : ''; ?>" id="vendasMenu">
                <i class="fas fa-shopping-cart"></i>
                <span>Vendas</span>
            </div>
            <div class="submenu <?php echo in_array($current_page, $vendasPages) ? 'active' : ''; ?>" id="vendasSubmenu">
                <a href="<?php echo url('vendas.php'); ?>" class="submenu-item <?php echo $current_page == 'vendas.php' ? 'active' : ''; ?>">
                    <i class="fas fa-list"></i>
                    <span>Lista de Vendas</span>
                </a>
                <a href="<?php echo url('acoes_vendas/nova-venda.php'); ?>" class="submenu-item <?php echo $current_page == 'nova-venda.php' ? 'active' : ''; ?>">
                    <i class="fas fa-plus"></i>
                    <span>Nova Venda</span>
                </a>
                <a href="<?php echo url('acoes_vendas/historico-vendas.php'); ?>" class="submenu-item <?php echo $current_page == 'historico-vendas.php' ? 'active' : ''; ?>">
                    <i class="fas fa-history"></i>
                    <span>Histórico</span>
                </a>
            </div>
        </div>
        
        <div class="menu-group">
            <div class="menu-group-title">Financeiro</div>
            
            <!-- Finanças -->
            <div class="menu-item has-submenu <?php echo in_array($current_page, $financasPages) ? 'active' : ''; ?>" id="financasMenu">
                <i class="fas fa-file-invoice-dollar"></i>
                <span>Finanças</span>
            </div>
            <div class="submenu <?php echo in_array($current_page, $financasPages) ? 'active' : ''; ?>" id="financasSubmenu">
                <a href="<?php echo url('tipopagamentos.php'); ?>" class="submenu-item <?php echo $current_page == 'tipopagamentos.php' ? 'active' : ''; ?>">
                    <i class="fas fa-credit-card"></i>
                    <span>Tipos de Pagamento</span>
                </a>
                <a href="<?php echo url('condicoesfaturamentos.php'); ?>" class="submenu-item <?php echo $current_page == 'condicoesfaturamentos.php' ? 'active' : ''; ?>">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Cond. Faturamento</span>
                </a>
                <a href="<?php echo url('tipodespesas.php'); ?>" class="submenu-item <?php echo $current_page == 'tipodespesas.php' ? 'active' : ''; ?>">
                    <i class="fas fa-receipt"></i>
                    <span>Tipos de Despesas</span>
                </a>
                <a href="<?php echo url('centrocusto.php'); ?>" class="submenu-item <?php echo $current_page == 'centrocusto.php' ? 'active' : ''; ?>">
                    <i class="fas fa-sitemap"></i>
                    <span>Centro de Custos</span>
                </a>
            </div>
            
            <!-- Contas -->
            <div class="menu-item has-submenu <?php echo in_array($current_page, $contasPages) ? 'active' : ''; ?>" id="contasMenu">
                <i class="fas fa-calculator"></i>
                <span>Contas</span>
            </div>
            <div class="submenu <?php echo in_array($current_page, $contasPages) ? 'active' : ''; ?>" id="contasSubmenu">
                <a href="<?php echo url('contaspagar.php'); ?>" class="submenu-item <?php echo $current_page == 'contaspagar.php' ? 'active' : ''; ?>">
                    <i class="fas fa-money-bill-wave"></i>
                    <span>Contas a Pagar</span>
                </a>
                <a href="<?php echo url('contas-receber.php'); ?>" class="submenu-item <?php echo $current_page == 'contas-receber.php' ? 'active' : ''; ?>">
                    <i class="fas fa-hand-holding-usd"></i>
                    <span>Contas a Receber</span>
                </a>
                <a href="<?php echo url('relatorio-contas.php'); ?>" class="submenu-item <?php echo $current_page == 'relatorio-contas.php' ? 'active' : ''; ?>">
                    <i class="fas fa-chart-bar"></i>
                    <span>Relatórios</span>
                </a>
            </div>
        </div>
        
        <div class="menu-group">
            <div class="menu-group-title">Sistema</div>
            <a href="<?php echo url('configuracoes.php'); ?>" class="menu-item <?php echo $current_page == 'configuracoes.php' ? 'active' : ''; ?>">
                <i class="fas fa-cog"></i>
                <span>Configurações</span>
            </a>
            <a href="<?php echo url('usuarios.php'); ?>" class="menu-item <?php echo $current_page == 'usuarios.php' ? 'active' : ''; ?>">
                <i class="fas fa-user-shield"></i>
                <span>Usuários</span>
            </a>
            <a href="<?php echo url('logout.php'); ?>" class="menu-item" id="logoutLink">
                <i class="fas fa-sign-out-alt"></i>
                <span>Sair</span>
            </a>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const menuToggle = document.getElementById('menuToggle');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlay');
    const mainContent = document.querySelector('.main-content');
    const menuItems = document.querySelectorAll('.menu-item');
    const hasSubmenuItems = document.querySelectorAll('.menu-item.has-submenu');
    
    // Toggle menu
    function toggleMenu() {
        const isActive = sidebar.classList.contains('active');
        
        sidebar.classList.toggle('active');
        overlay.classList.toggle('active');
        menuToggle.classList.toggle('active');
        
        if (mainContent) {
            mainContent.classList.toggle('menu-open');
        }
        
        // Atualiza ícone do botão com animação
        const icon = menuToggle.querySelector('i');
        if (!isActive) {
            icon.className = 'fas fa-times';
            document.body.style.overflow = 'hidden';
        } else {
            icon.className = 'fas fa-bars';
            document.body.style.overflow = 'auto';
        }
    }
    
    // Função para alternar submenus
    function toggleSubmenu(menuItem) {
        const submenuId = menuItem.id.replace('Menu', 'Submenu');
        const submenu = document.getElementById(submenuId);
        
        // Fecha todos os outros submenus
        document.querySelectorAll('.submenu').forEach(sm => {
            if (sm.id !== submenuId) {
                sm.classList.remove('active');
            }
        });
        
        // Remove a classe active de todos os itens de menu com submenu
        document.querySelectorAll('.menu-item.has-submenu').forEach(item => {
            if (item.id !== menuItem.id) {
                item.classList.remove('active');
            }
        });
        
        // Alterna o submenu atual
        submenu.classList.toggle('active');
        menuItem.classList.toggle('active');
    }
    
    // Event listeners
    menuToggle.addEventListener('click', toggleMenu);
    overlay.addEventListener('click', toggleMenu);
    
    // Event listeners para itens com submenu
    hasSubmenuItems.forEach(item => {
        item.addEventListener('click', function(e) {
            e.preventDefault();
            toggleSubmenu(this);
        });
    });
    
    // Fecha menu ao clicar em item sem submenu
    menuItems.forEach(item => {
        if (!item.classList.contains('has-submenu')) {
            item.addEventListener('click', function() {
                if (sidebar.classList.contains('active')) {
                    setTimeout(() => {
                        toggleMenu();
                    }, 150);
                }
            });
        }
    });
    
    // Fecha menu com tecla ESC
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && sidebar.classList.contains('active')) {
            toggleMenu();
        }
    });
    
    // Verifica se há submenu ativo e mantém aberto
    function checkActiveSubmenus() {
        const currentPage = window.location.pathname.split('/').pop() || 'index.php';
        
        hasSubmenuItems.forEach(item => {
            const submenuId = item.id.replace('Menu', 'Submenu');
            const submenu = document.getElementById(submenuId);
            const submenuItems = submenu.querySelectorAll('.submenu-item');
            
            let isActiveSubmenu = false;
            submenuItems.forEach(subItem => {
                const href = subItem.getAttribute('href');
                const hrefPage = href.split('/').pop();
                
                if (hrefPage === currentPage) {
                    isActiveSubmenu = true;
                    subItem.classList.add('active');
                }
            });
            
            if (isActiveSubmenu) {
                item.classList.add('active');
                submenu.classList.add('active');
            }
        });
    }
    
    // Executa a verificação ao carregar a página
    checkActiveSubmenus();
    
    // Animação no hover do toggle
    menuToggle.addEventListener('mouseenter', function() {
        if (!this.classList.contains('active')) {
            this.style.transform = 'translateY(-2px) scale(1.05)';
        }
    });
    
    menuToggle.addEventListener('mouseleave', function() {
        this.style.transform = 'translateY(0) scale(1)';
    });
});
</script>
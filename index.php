<?php
include 'conexao.php'; // Inclui o arquivo de conexão

// Função para detectar o tipo de dispositivo
function isMobileDevice() {
    return preg_match("/(android|avantgo|blackberry|bolt|boost|cricket|docomo|fone|hiptop|mini|mobi|palm|phone|pie|tablet|up\.browser|up\.link|webos|wos)/i", $_SERVER["HTTP_USER_AGENT"]);
}

// Determinar o tipo de dispositivo
$tipo_dispositivo = isMobileDevice() ? 'mobile' : 'desktop';

// Função para buscar a URL da imagem
function buscarImagemURL($nome) {
    return "imagem.php?nome=" . urlencode($nome);
}

// Buscar URLs das imagens
$banner_csemp_url = buscarImagemURL('banner-1');
$csbar_url = buscarImagemURL('banner-2');
$csemitehome_url = buscarImagemURL('banner-3');
$mercado_url = buscarImagemURL("mercado"); // Manter como está, se não foi migrado para a tabela banners
$mecanico_url = buscarImagemURL("mecanico"); // Manter como está, se não foi migrado para a tabela banners
$lojaroupa_url = buscarImagemURL("lojaroupa"); // Manter como está, se não foi migrado para a tabela banners
$lanchonete_url = buscarImagemURL("lanchonete"); // Manter como está, se não foi migrado para a tabela banners
$agropecuaria_url = buscarImagemURL("agropecuaria"); // Manter como está, se não foi migrado para a tabela banners

$conn->close();
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Início - Calory Sistemas</title>
  <link rel="icon" href="favicon.ico" type="image/x-icon" />
  <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
  <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
  <link rel="manifest" href="/site.webmanifest">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
  <link rel="stylesheet" href="styleshome.min.css">
  <!-- Google tag (gtag.js) -->
  <script async src="https://www.googletagmanager.com/gtag/js?id=AW-17383219009"></script>
  <script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    gtag('js', new Date());
    gtag('config', 'AW-17383219009');
  </script>
  <style>
    /* Reset e Estilos Gerais */
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: 'Poppins', sans-serif;
      background-color: #f9f9f9;
      color: #333;
      line-height: 1.6;
      scroll-behavior: smooth;
      padding-top: 80px;
    }

    /* Header */
    .calory-header {
      width: 100%;
      padding: 15px 5%;
      background: rgba(255, 255, 255, 0.85);
      backdrop-filter: blur(12px);
      -webkit-backdrop-filter: blur(12px);
      box-shadow: 0 4px 30px rgba(0, 0, 0, 0.1);
      position: fixed;
      top: 0;
      left: 0;
      z-index: 1000;
      transition: all 0.3s ease;
      border-bottom: 1px solid rgba(255, 255, 255, 0.3);
    }

    .calory-header.scrolled {
      padding: 10px 5%;
      background: rgba(255, 255, 255, 0.95);
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
    }

    .header-container {
      max-width: 1200px;
      margin: 0 auto;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .calory-logo {
      display: flex;
      align-items: center;
    }

    .calory-logo img {
      height: 50px;
      transition: height 0.3s ease;
    }

    .calory-header.scrolled .calory-logo img {
      height: 40px;
    }

    /* Menu Mobile */
    .calory-menu-toggle {
      display: none;
      width: 30px;
      height: 25px;
      position: relative;
      cursor: pointer;
      z-index: 1001;
      background: none;
      border: none;
      padding: 0;
    }

    .calory-menu-toggle span {
      display: block;
      position: absolute;
      height: 3px;
      width: 100%;
      background: #007bff;
      border-radius: 3px;
      opacity: 1;
      left: 0;
      transform: rotate(0deg);
      transition: .25s ease-in-out;
    }

    .calory-menu-toggle span:nth-child(1) {
      top: 0px;
    }

    .calory-menu-toggle span:nth-child(2),
    .calory-menu-toggle span:nth-child(3) {
      top: 10px;
    }

    .calory-menu-toggle span:nth-child(4) {
      top: 20px;
    }

    .calory-menu-toggle.active span:nth-child(1) {
      top: 10px;
      width: 0%;
      left: 50%;
    }

    .calory-menu-toggle.active span:nth-child(2) {
      transform: rotate(45deg);
    }

    .calory-menu-toggle.active span:nth-child(3) {
      transform: rotate(-45deg);
    }

    .calory-menu-toggle.active span:nth-child(4) {
      top: 10px;
      width: 0%;
      left: 50%;
    }

    /* Navegação */
    .calory-nav-links {
      display: flex;
      align-items: center;
      gap: 1.5rem;
    }

    .calory-nav-links a {
      font-weight: 600;
      color: #333;
      text-decoration: none;
      font-size: 1rem;
      position: relative;
      padding: 8px 0;
      transition: color 0.3s ease;
    }

    .calory-nav-links a:not(.calory-link-azul):hover {
      color: #007bff;
    }

    .calory-nav-links a:not(.calory-link-azul)::after {
      content: '';
      position: absolute;
      width: 0;
      height: 2px;
      bottom: 0;
      left: 0;
      background-color: #007bff;
      transition: width 0.3s ease;
    }

    .calory-nav-links a:not(.calory-link-azul):hover::after {
      width: 100%;
    }

    .calory-link-azul {
      background: linear-gradient(135deg, #007bff, #00bfff);
      color: #fff !important;
      padding: 12px 25px;
      border-radius: 50px;
      font-weight: 600;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      transition: all 0.3s ease;
      box-shadow: 0 4px 15px rgba(0, 123, 255, 0.3);
      position: relative;
      overflow: hidden;
    }

    .calory-link-azul i {
      margin-right: 10px;
      transition: transform 0.3s ease;
    }

    .calory-link-azul:hover {
      background: linear-gradient(135deg, #0069d9, #0095ff);
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(0, 123, 255, 0.4);
    }

    .calory-link-azul:hover i {
      transform: translateX(5px);
    }

    .calory-link-azul::before {
      content: '';
      position: absolute;
      top: -20px;
      left: -20px;
      right: -20px;
      bottom: -20px;
      background: radial-gradient(circle, rgba(255,255,255,0.8) 0, rgba(255,255,255,0) 70%);
      opacity: 0;
      transition: opacity 0.3s ease;
    }

    .calory-link-azul:hover::before {
      opacity: 0.4;
    }

    /* Banner Carousel */
    .calory-banner-carousel {
      position: relative;
      height: 85vh;
      width: 100%;
      margin-top: 0;
      overflow: hidden;
    }

    .calory-banner-track {
      display: flex;
      height: 100%;
      transition: transform 0.5s ease;
    }

    .calory-banner-slide img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    .calory-banner-overlay {
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: linear-gradient(to bottom, rgba(255,255,255,0.15), rgba(22,98,184,0.3));
      z-index: 1;
    }

    .calory-banner-overlay1 {
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: linear-gradient(to bottom, rgba(255,255,255,0.15), rgba(184,146,22,0.3));
      z-index: 1;
    }

    .calory-banner-overlay2 {
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: linear-gradient(to bottom, rgba(255,255,255,0.15), rgba(40,167,69,0.3));
      z-index: 1;
    }

    .calory-banner-content {
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      text-align: center;
      color: #fff;
      padding: 0 20px;
      z-index: 2;
    }

    .calory-banner-content h1 {
      font-size: 3.1rem;
      font-weight: 700;
      margin-bottom: 20px;
      color: #fff;
      text-shadow: 0 2px 4px rgba(158,158,158,0.6);
      line-height: 1.2;
    }

    .calory-banner-content p {
      font-size: 1.5rem;
      font-weight: 500;
      margin-bottom: 30px;
      max-width: 800px;
      text-shadow: 0 1px 3px rgba(0,0,0,0.3);
    }

    /* Botões CTA Melhorados */
    .calory-cta-button {
      display: inline-flex;
      align-items: center;
      padding: 15px 30px;
      border-radius: 50px;
      font-weight: 700;
      font-size: 1.2rem;
      color: white !important;
      text-decoration: none;
      margin-top: 1%;
      transition: all 0.3s ease;
      box-shadow: 0 4px 15px rgba(0,0,0,0.2);
      position: relative;
      overflow: hidden;
    }

    .calory-cta-button1 {
      display: inline-flex;
      align-items: center;
      padding: 15px 30px;
      border-radius: 50px;
      font-weight: 700;
      font-size: 1.2rem;
      color: white !important;
      text-decoration: none;
      margin-top: 13%;
      transition: all 0.3s ease;
      box-shadow: 0 4px 15px rgba(0,0,0,0.2);
      position: relative;
      overflow: hidden;
    }

    .calory-cta-button i {
      margin-right: 10px;
      font-size: 1.2rem;
    }

    .calory-cta-button1 i {
      margin-right: 10px;
      font-size: 1.2rem;
    }
    

    .calory-cta-button:hover {
      transform: translateY(-3px);
      box-shadow: 0 8px 25px rgba(0,0,0,0.3);
    }

    .calory-cta-button1:hover {
      transform: translateY(-3px);
      box-shadow: 0 8px 25px rgba(0,0,0,0.3);
    }

    .calory-cta-button::after {
      content: '';
      position: absolute;
      top: -50%;
      left: -50%;
      width: 200%;
      height: 200%;
      background: rgba(255,255,255,0.1);
      transform: rotate(45deg);
      transition: all 0.3s ease;
    }

    .calory-cta-button1::after {
      content: '';
      position: absolute;
      top: -50%;
      left: -50%;
      width: 200%;
      height: 200%;
      background: rgba(255,255,255,0.1);
      transform: rotate(45deg);
      transition: all 0.3s ease;
    }

    .calory-cta-button:hover::after {
      left: 100%;
    }

    .calory-cta-button1:hover::after {
      left: 100%;
    }

    /* Formulário de Orçamento - Modificado para iniciar minimizado */
    .calory-form-section {
      background: #f8f9fa;
      padding: 20px 5%;
      text-align: center;
      transition: all 0.3s ease;
      max-height: 100px;
      overflow: hidden;
    }

    .calory-form-section.expanded {
      max-height: 1000px;
      padding: 60px 5%;
    }

    .calory-form-toggle {
      background: linear-gradient(135deg, #007bff, #00bfff);
      color: white;
      border: none;
      padding: 10px 80px;
      font-size: 1.5rem;
      border-radius: 50px;
      font-weight: 600;
      cursor: pointer;
      margin: 10px auto;
      display: block;
      transition: all 0.3s ease;
      box-shadow: 0 4px 10px rgba(0,123,255,0.2);
    }

    .calory-form-toggle:hover {
      background: linear-gradient(135deg, #0069d9, #0095ff);
      transform: translateY(-2px);
      box-shadow: 0 6px 15px rgba(0,123,255,0.3);
    }

    .calory-form-toggle i {
      margin-left: 8px;
      transition: transform 0.3s ease;
    }

    .calory-form-section.expanded .calory-form-toggle i {
      transform: rotate(180deg);
    }

    .form-container {
      max-width: 600px;
      margin: 20px auto 0;
      background: white;
      padding: 40px;
      border-radius: 10px;
      box-shadow: 0 5px 15px rgba(0,0,0,0.1);
      opacity: 0;
      transition: opacity 0.5s ease;
    }

    .calory-form-section.expanded .form-container {
      opacity: 1;
    }

    .calory-form-section h2 {
      color: #007bff;
      margin-bottom: 15px;
      font-size: 2rem;
      opacity: 0;
      transition: opacity 0.3s ease;
    }

    .calory-form-section.expanded h2 {
      opacity: 1;
    }

    .calory-form-section p {
      color: #666;
      margin-bottom: 30px;
      font-size: 1.1rem;
      opacity: 0;
      transition: opacity 0.3s ease;
    }

    .calory-form-section.expanded p {
      opacity: 1;
    }

    .form-group {
      margin-bottom: 20px;
      text-align: left;
    }

    .form-group input,
    .form-group select {
      width: 100%;
      padding: 15px;
      border: 1px solid #ddd;
      border-radius: 5px;
      font-size: 1rem;
      transition: all 0.3s ease;
    }

    .form-group input:focus,
    .form-group select:focus {
      border-color: #007bff;
      box-shadow: 0 0 0 3px rgba(0,123,255,0.1);
    }

    .calory-form-button {
      background: linear-gradient(135deg, #007bff, #00bfff);
      color: white;
      border: none;
      padding: 15px 30px;
      border-radius: 50px;
      font-weight: 600;
      font-size: 1.1rem;
      cursor: pointer;
      width: 100%;
      transition: all 0.3s ease;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
    }

    .calory-form-button:hover {
      background: linear-gradient(135deg, #0069d9, #0095ff);
      transform: translateY(-2px);
      box-shadow: 0 5px 15px rgba(0,123,255,0.3);
    }

    /* Seção de Segmentos - VERSÃO CORRIGIDA */
    .calory-segmentos-section {
      padding: 60px 5%;
      text-align: center;
      background-color: #f9f9f9;
    }

    .calory-segmentos-section h2 {
      font-size: 2rem;
      color: #007bff;
      margin-bottom: 40px;
    }

    .calory-segmentos-container {
      position: relative;
      max-width: 1200px;
      margin: 0 auto;
      overflow: hidden;
      padding: 0 50px;
    }

    .calory-segmentos-wrapper {
      width: 100%;
      overflow: hidden;
      position: relative;
    }

    .calory-segmentos-carousel {
      overflow-x: auto;
      scroll-behavior: smooth;
      -webkit-overflow-scrolling: touch;
      scroll-snap-type: x mandatory;
      scrollbar-width: none; /* Firefox */
      -ms-overflow-style: none; /* IE and Edge */
    }

    .calory-segmentos-carousel::-webkit-scrollbar {
      display: none; /* Chrome, Safari, Opera */
    }

    .calory-segmentos-track {
      display: flex;
      transition: transform 0.5s ease;
      width: max-content;
    }

    .calory-segmento-card {
      scroll-snap-align: start;
      min-width: 280px;
      max-width: 280px;
      margin: 0 15px;
      background: white;
      border-radius: 10px;
      overflow: hidden;
      box-shadow: 0 5px 15px rgba(0,0,0,0.1);
      transition: transform 0.3s ease, box-shadow 0.3s ease;
      text-decoration: none;
      color: #333;
    }

    .calory-segmento-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 10px 25px rgba(0,0,0,0.15);
    }

    .calory-segmento-img {
      height: 180px;
      overflow: hidden;
    }

    .calory-segmento-img img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      transition: transform 0.5s ease;
    }

    .calory-segmento-card:hover .calory-segmento-img img {
      transform: scale(1.05);
    }

    .calory-segmento-content {
      padding: 20px;
      text-align: center;
    }

    .calory-segmento-content h3 {
      font-size: 1.2rem;
      margin-bottom: 10px;
      color: #007bff;
    }

    .calory-segmento-content p {
      font-size: 0.9rem;
      margin-bottom: 15px;
      color: #666;
    }

    .calory-segmento-link {
      display: inline-block;
      color: #007bff;
      font-weight: 600;
      font-size: 0.9rem;
      transition: color 0.3s ease;
    }

    .calory-segmento-card:hover .calory-segmento-link {
      color: #0056b3;
    }

    .calory-segmentos-arrow {
      position: absolute;
      top: 50%;
      transform: translateY(-50%);
      width: 40px;
      height: 40px;
      background-color: white;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      z-index: 10;
      font-size: 20px;
      color: #007bff;
      border: none;
      transition: all 0.3s ease;
    }

    .calory-segmentos-arrow:hover {
      background-color: #007bff;
      color: white;
      box-shadow: 0 4px 15px rgba(0,123,255,0.3);
    }

    .calory-segmentos-arrow.left {
      left: 0;
    }

    .calory-segmentos-arrow.right {
      right: 0;
    }

    .calory-segmentos-nav {
      display: flex;
      justify-content: center;
      gap: 10px;
      margin-top: 20px;
    }

    .calory-segmentos-dot {
      width: 12px;
      height: 12px;
      border-radius: 50%;
      background-color: rgba(0,123,255,0.3);
      cursor: pointer;
      transition: all 0.3s ease;
    }

    .calory-segmentos-dot.active {
      background-color: #007bff;
      transform: scale(1.2);
    }

    /* Seção de Vídeo */
    .calory-video-section {
      padding: 60px 5%;
      background: #f8f9fa;
      text-align: center;
    }
    
    .calory-video-container {
      max-width: 900px;
      margin: 0 auto;
      position: relative;
      padding-bottom: 56.25%;
      height: 0;
      overflow: hidden;
      border-radius: 12px;
      box-shadow: 0 10px 30px rgba(0,0,0,0.15);
    }
    
    .calory-video-container iframe {
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      border: none;
    }
    
    .calory-video-section h2 {
      font-size: 2.2rem;
      color: #007bff;
      margin-bottom: 20px;
    }
    
    .calory-video-section p {
      font-size: 1.1rem;
      color: #555;
      max-width: 700px;
      margin: 0 auto 30px;
    }

    /* Seções de Benefícios */
    .por-que-escolher {
      padding: 50px 10%;
      background-color: #fff;
      text-align: center;
      margin: 60px 0;
    }

    .por-que-escolher h2 {
      font-size: 2rem;
      margin-bottom: 30px;
    }

    .por-que-container {
      display: flex;
      justify-content: center;
      gap: 40px;
      flex-wrap: wrap;
    }

    .por-que-item {
      max-width: 250px;
    }

    .por-que-item img {
      width: 50px;
      margin-bottom: 10px;
    }

    .por-que-item h3 {
      color: #29a8ff;
      margin-bottom: 5px;
    }

    .como-funciona {
      padding: 50px 10%;
      background: #fff;
      text-align: center;
      margin: 60px 0;
    }

    .como-funciona h2 {
      font-size: 2rem;
      margin-bottom: 30px;
    }

    .como-container {
      display: flex;
      justify-content: space-around;
      flex-wrap: wrap;
    }

    .como-item {
      max-width: 200px;
    }

    .como-item img {
      width: 40px;
      margin-bottom: 10px;
    }

    .como-item h4 {
      margin-bottom: 8px;
      color: #29a8ff;
    }

    /* Botão do WhatsApp */
    .calory-whatsapp-float {
      position: fixed;
      bottom: 30px;
      right: 30px;
      z-index: 1000;
    }

    .calory-whatsapp-float a {
      display: flex;
      align-items: center;
      gap: 8px;
      text-decoration: none;
    }

    .calory-whatsapp-float .calory-whatsapp-icon {
      width: 60px;
      height: 60px;
      border-radius: 50%;
      background-color: #25d366;
      display: flex;
      align-items: center;
      justify-content: center;
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
      transition: transform 0.3s ease;
    }

    .calory-whatsapp-float .calory-whatsapp-icon img {
      width: 30px;
      height: 30px;
    }

    .calory-whatsapp-float:hover .calory-whatsapp-icon {
      transform: scale(1.1);
    }

    /* Footer */
    .calory-footer {
      background-color: #2c3e50;
      color: #fff;
      padding: 60px 5% 30px;
      font-size: 0.9rem;
    }

    .footer-container {
      max-width: 1200px;
      margin: 0 auto;
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 30px;
    }

    .footer-logo img {
      height: 50px;
      margin-bottom: 20px;
    }

    .footer-logo p {
      margin-bottom: 20px;
      line-height: 1.6;
    }

    .footer-links h3 {
      font-size: 1.2rem;
      margin-bottom: 20px;
      color: #fff;
    }

    .footer-links ul {
      list-style: none;
    }

    .footer-links li {
      margin-bottom: 10px;
    }

    .footer-links a {
      color: #ecf0f1;
      text-decoration: none;
      transition: color 0.3s ease;
    }

    .footer-links a:hover {
      color: #007bff;
    }

    .footer-social h3 {
      font-size: 1.2rem;
      margin-bottom: 20px;
      color: #fff;
    }

    .footer-social .social-icons {
      display: flex;
      gap: 15px;
    }

    .footer-social .social-icons a {
      display: flex;
      align-items: center;
      justify-content: center;
      width: 40px;
      height: 40px;
      background: rgba(255,255,255,0.1);
      border-radius: 50%;
      color: #fff;
      font-size: 1.1rem;
      transition: all 0.3s ease;
    }

    .footer-social .social-icons a:hover {
      background: #007bff;
      transform: translateY(-3px);
    }

    .footer-bottom {
      text-align: center;
      padding-top: 30px;
      margin-top: 30px;
      border-top: 1px solid rgba(255,255,255,0.1);
    }

    /* Responsividade */
    @media (max-width: 1199.98px) {
      .calory-banner-content h1 {
        font-size: 2.5rem;
      }
      
      .calory-banner-content p {
        font-size: 1.3rem;
      }
      
      .calory-cta-button {
        padding: 12px 25px;
        font-size: 1.1rem;
      }
    }

    @media (max-width: 899.98px) {
      body {
        padding-top: 70px;
      }
      
      .calory-header {
        padding: 10px 5%;
      }
      
      .calory-menu-toggle {
        display: flex;
      }
      
      .calory-nav-links {
        position: fixed;
        top: 0;
        right: -100%;
        width: 280px;
        height: 100vh;
        background-color: rgba(255,255,255,0.95);
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        flex-direction: column;
        padding: 80px 30px 30px;
        box-shadow: -5px 0 15px rgba(0,0,0,0.1);
        transition: right 0.3s ease;
      }
      
      .calory-nav-links.active {
        right: 0;
      }
      
      .calory-nav-links a {
        padding: 15px 0;
        border-bottom: 1px solid rgba(0,0,0,0.1);
        width: 100%;
      }
      
      .calory-banner-carousel {
        height: 60vh;
      }
      
      .calory-banner-content h1 {
        font-size: 2rem;
        margin-bottom: 15px;
      }
      
      .calory-banner-content p {
        font-size: 1.1rem;
        margin-bottom: 20px;
      }
      
      .calory-cta-button {
        padding: 12px 25px;
        font-size: 1rem;
        margin-top: 30% !important;
        z-index: 3;
        position: relative;
      }

      .calory-cta-button1 {
        padding: 12px 25px;
        font-size: 1rem;
        margin-top: 30% !important;
        z-index: 3;
        position: relative;
      }
      
      .calory-form-section h2 {
        font-size: 1.8rem;
      }
      
      .form-container {
        padding: 25px 20px;
      }
      
      .calory-segmentos-container {
        padding: 0 30px;
      }

      .calory-segmento-card {
        min-width: 240px;
        max-width: 240px;
      }
    }

    @media (max-width: 767.98px) {
      .calory-banner-carousel {
        height: 60vh;
      }
      
      .calory-banner-content h1 {
        font-size: 1.8rem;
      }
      
      .calory-cta-button1 {
        padding: 16px 16px;
        margin-top: 35% !important;
        font-size: 0.9rem;
      }
      
      .calory-form-toggle {
        padding: 8px 30px;
        font-size: 1.1rem;
      }

      .calory-segmentos-arrow {
        width: 35px;
        height: 35px;
        font-size: 16px;
      }
    }

    @media (max-width: 575.98px) {
      .calory-banner-carousel {
        height: 55vh;
      }
      
      .calory-banner-content h1 {
        font-size: 1.6rem;
      }
      
      .calory-nav-links {
        width: 250px;
      }
      
      .calory-segmentos-container {
        padding: 0 20px;
      }

      .calory-segmento-card {
        min-width: 220px;
        max-width: 220px;
        margin: 0 10px;
      }
    }

    @media (max-width: 414px) {
      .calory-banner-carousel {
        height: 50vh;
      }
      
      .calory-banner-content h1 {
        font-size: 1.4rem;
      }
      
      .calory-cta-button1 {
        padding: 16px 16px;
        margin-top: 35% !important;
        font-size: 0.9rem;
      }
      
      .calory-whatsapp-float .calory-whatsapp-icon {
        width: 50px;
        height: 50px;
      }
      
      .calory-whatsapp-float .calory-whatsapp-icon img {
        width: 25px;
        height: 25px;
      }

      .calory-segmento-card {
        min-width: 200px;
        max-width: 200px;
      }
    }

    @media (max-width: 320px) {
      .calory-banner-carousel {
        height: 45vh;
      }
      
      .calory-banner-content h1 {
        font-size: 1.4rem;
      }
      
      .calory-cta-button {
        padding: 7px 14px;
        font-size: 0.8rem;
      }

      .calory-segmento-card {
        min-width: 180px;
        max-width: 180px;
      }
    }

    @media (max-height: 500px) and (orientation: landscape) {
      .calory-banner-carousel {
        height: 100vh;
      }
      
      .calory-banner-content h1 {
        font-size: 2rem;
        margin-bottom: 5%;
      }
      
      .calory-banner-content p {
        font-size: 1.2rem;
      }
    }

    /* Estilos para as setas do carrossel */
    .calory-carousel-arrow {
      position: absolute;
      top: 50%;
      transform: translateY(-50%);
      width: 50px;
      height: 50px;
      background-color: rgba(255,255,255,0.7);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      z-index: 10;
      font-size: 24px;
      color: #007bff;
      border: none;
      transition: all 0.3s ease;
    }

    .calory-carousel-arrow:hover {
      background-color: rgba(255,255,255,0.9);
    }

    .calory-carousel-arrow.left {
      left: 20px;
    }

    .calory-carousel-arrow.right {
      right: 20px;
    }

    /* Indicadores do carrossel */
    .calory-banner-nav {
      position: absolute;
      bottom: 20px;
      left: 50;
      right: 0;
      display: flex;
      justify-content: center;
      gap: 10px;
      z-index: 10;
    }

    .calory-banner-dot {
      width: 12px;
      height: 12px;
      border-radius: 50%;
      background-color: rgba(255,255,255,0.5);
      cursor: pointer;
      transition: all 0.3s ease;
    }

    .calory-banner-dot.active {
      background-color: white;
      transform: scale(1.2);
    }
  </style>
</head>

<body>
  <header class="calory-header">
    <div class="header-container">
      <div class="calory-logo">
        <a href="/">
          <img src="calory png.png" alt="Logo Calory">
        </a>
      </div>
      
      <button class="calory-menu-toggle" onclick="caloryToggleMenu()">
        <span></span>
        <span></span>
        <span></span>
      </button>
      
      <nav class="calory-nav-links">
        <a href="/">Início</a>
        <a href="/produtos">Produtos</a>
        <a href="/contato">Contato</a>
        <a class="calory-link-azul" href="https://api.whatsapp.com/send?phone=5544999939313&text=Olá,%20vim%20pelo%20site%20e%20gostaria%20de%20solicitar%20um%20orçamento%20para%20o%20meu%20sistema!">
          <i class="fas fa-rocket"></i> Obtenha o sistema
        </a>
      </nav>
    </div>
  </header>

  <!-- Banner Carousel -->
  <div class="calory-banner-carousel">
    <button class="calory-carousel-arrow left" onclick="prevBannerSlide()">❮</button>
    <button class="calory-carousel-arrow right" onclick="nextBannerSlide()">❯</button>
    
    <div class="calory-banner-track" id="bannerTrack">
      <!-- Slide 1 - CSEMP -->
      <div class="calory-banner-slide">
        <?php if ($banner_csemp_url): ?>
            <img src="<?php echo $banner_csemp_url; ?>" alt="Sistema para Mercados">
        <?php else: ?>
            <p>Imagem do banner 1 não encontrada.</p>
        <?php endif; ?>
        <div class="calory-banner-overlay"></div>
        <div class="calory-banner-content">
          <a href="#form-orcamento" class="calory-cta-button1" style="background-color: #007bff;" onclick="expandFormSection()">
            <i class="fas fa-clock"></i> TESTE GRÁTIS POR 30 DIAS
          </a>
        </div>
      </div>
      
      <!-- Slide 2 - CSBAR -->
      <div class="calory-banner-slide">
        <?php if ($csbar_url): ?>
            <img src="<?php echo $csbar_url; ?>" alt="Sistema para Restaurantes">
        <?php else: ?>
            <p>Imagem do banner 2 não encontrada.</p>
        <?php endif; ?>
        <div class="calory-banner-overlay1"></div>
        <div class="calory-banner-content">
          <a href="#form-orcamento" class="calory-cta-button" style="background-color: #ff8c00;" onclick="expandFormSection()">
            <i class="fas fa-utensils"></i> SAIBA MAIS
          </a>
        </div>
      </div>
      
      <!-- Slide 3 - CSEMITE -->
      <div class="calory-banner-slide">
        <?php if ($csemitehome_url): ?>
            <img src="<?php echo $csemitehome_url; ?>" alt="Gestão Financeira">
        <?php else: ?>
            <p>Imagem do banner 3 não encontrada.</p>
        <?php endif; ?>
        <div class="calory-banner-overlay2"></div>
        <div class="calory-banner-content">
          <a href="#form-orcamento" class="calory-cta-button" style="background-color: #28a745;" onclick="expandFormSection()">
            <i class="fas fa-file-invoice-dollar"></i> FALE COM UM CONSULTOR
          </a>
        </div>
      </div>
    </div>
    
    <div class="calory-banner-nav" id="bannerNav">
      <div class="calory-banner-dot active" onclick="goToBannerSlide(0)"></div>
      <div class="calory-banner-dot" onclick="goToBannerSlide(1)"></div>
      <div class="calory-banner-dot" onclick="goToBannerSlide(2)"></div>
    </div>
  </div>

  <!-- Formulário de Orçamento - Modificado para iniciar minimizado -->
  <section id="form-orcamento" class="calory-form-section">
    <button class="calory-form-toggle" onclick="toggleFormSection()">
      Solicitar Teste Grátis <i class="fas fa-chevron-down"></i>
    </button>
    
    <div class="form-container">
      <h2>Receba 30 dias para testar o nosso sistema</h2>
      <p>Preencha os dados abaixo e nosso consultor entrará em contato em até 15 minutos</p>
      
      <form id="orcamentoForm" action="https://formsubmit.co/comercialcalory@gmail.com" method="POST">
        <input type="hidden" name="_captcha" value="false">
        <input type="hidden" name="_next" value="https://calory.com.br/obrigado">
        
        <div class="form-group">
          <input type="text" name="nome" placeholder="Seu nome completo" required>
        </div>
        
        <div class="form-group">
          <input type="tel" name="telefone" placeholder="WhatsApp com DDD" required>
        </div>
        
        <div class="form-group">
          <input type="email" name="email" placeholder="Seu melhor e-mail" required>
        </div>
        
        <div class="form-group">
          <select name="segmento" required>
            <option value="" disabled selected>Qual seu segmento?</option>
            <option value="Mercado/Supermercado">Mercado/Supermercado</option>
            <option value="Restaurante/Lanchonete">Restaurante/Lanchonete</option>
            <option value="Varejo/Loja">Varejo/Loja</option>
            <option value="Serviços">Serviços</option>
            <option value="Outros">Outros</option>
          </select>
        </div>
        
        <div class="form-group">
          <select name="sistema_interesse" required>
            <option value="" disabled selected>Qual sistema tem interesse?</option>
            <option value="CSEMP - Gestão Completa">CSEMP - Gestão Completa</option>
            <option value="CSBAR - Para bares/restaurantes">CSBAR - Para bares/restaurantes</option>
            <option value="CSEMITE - Notas fiscais">CSEMITE - Notas fiscais</option>
            <option value="Não sei, preciso de ajuda">Não sei, preciso de ajuda</option>
          </select>
        </div>
        
        <button type="submit" class="calory-form-button">
          <i class="fas fa-paper-plane"></i> ENVIAR SOLICITAÇÃO
        </button>
      </form>
    </div>
  </section>

  <!-- Soluções para diversos ramos de atividade - VERSÃO CORRIGIDA -->
  <section class="calory-segmentos-section">
    <h2>Soluções para diversos ramos de atividade</h2>
    <div class="calory-segmentos-container">
      <button class="calory-segmentos-arrow left" onclick="prevSegmentoSlide()">❮</button>
      <button class="calory-segmentos-arrow right" onclick="nextSegmentoSlide()">❯</button>
      
      <div class="calory-segmentos-wrapper">
        <div class="calory-segmentos-carousel" id="segmentosCarousel">
          <div class="calory-segmentos-track" id="segmentosTrack">
            <!-- Segmento 1 -->
            <a href="/mercados" class="calory-segmento-card">
              <div class="calory-segmento-img">
                <?php if ($mercado_url): ?>
                  <img src="<?php echo $mercado_url; ?>" alt="Mercados">
                <?php else: ?>
                  <img src="estabelecimentos/mercado.webp" alt="Mercados">
                <?php endif; ?>
              </div>
              <div class="calory-segmento-content">
                <h3>Mercados</h3>
                <p>Controle completo de estoque e vendas</p>
                <span class="calory-segmento-link">Clique para saber mais</span>
              </div>
            </a>
            
            <!-- Segmento 2 -->
            <a href="/lanchonete" class="calory-segmento-card">
              <div class="calory-segmento-img">
                <?php if ($lanchonete_url): ?>
                  <img src="<?php echo $lanchonete_url; ?>" alt="Restaurantes">
                <?php else: ?>
                  <img src="estabelecimentos/lanchonete.webp" alt="Restaurantes">
                <?php endif; ?>
              </div>
              <div class="calory-segmento-content">
                <h3>Restaurantes e Lanchonetes</h3>
                <p>Gestão de cardápios e comandas</p>
                <span class="calory-segmento-link">Clique para saber mais</span>
              </div>
            </a>
            
            <!-- Segmento 3 -->
            <a href="/agropecuaria" class="calory-segmento-card">
              <div class="calory-segmento-img">
                <?php if ($agropecuaria_url): ?>
                  <img src="<?php echo $agropecuaria_url; ?>" alt="Agropecuária">
                <?php else: ?>
                  <img src="estabelecimentos/agropecuaria.webp" alt="Agropecuária">
                <?php endif; ?>
              </div>
              <div class="calory-segmento-content">
                <h3>Agropecuária</h3>
                <p>Controle de insumos e produtos agrícolas</p>
                <span class="calory-segmento-link">Clique para saber mais</span>
              </div>
            </a>
            
            <!-- Segmento 4 -->
            <a href="/lojaderoupas" class="calory-segmento-card">
              <div class="calory-segmento-img">
                <?php if ($lojaroupa_url): ?>
                  <img src="<?php echo $lojaroupa_url; ?>" alt="Varejo">
                <?php else: ?>
                  <img src="estabelecimentos/lojaroupa.webp" alt="Varejo">
                <?php endif; ?>
              </div>
              <div class="calory-segmento-content">
                <h3>Lojas de Roupa e calçados</h3>
                <p>Gerencie suas peças</p>
                <span class="calory-segmento-link">Clique para saber mais</span>
              </div>
            </a>
            
            <!-- Segmento 5 -->
            <a href="/mecanico" class="calory-segmento-card">
              <div class="calory-segmento-img">
                <?php if ($mecanico_url): ?>
                  <img src="<?php echo $mecanico_url; ?>" alt="Oficinas">
                <?php else: ?>
                  <img src="estabelecimentos/mecanico.webp" alt="Oficinas">
                <?php endif; ?>
              </div>
              <div class="calory-segmento-content">
                <h3>Oficinas Mecânicas</h3>
                <p>Gestão de serviços e peças</p>
                <span class="calory-segmento-link">Clique para saber mais</span>
              </div>
            </a>
          </div>
        </div>
      </div>
      
      <div class="calory-segmentos-nav" id="segmentosNav">
        <div class="calory-segmentos-dot active" onclick="goToSegmentoSlide(0)"></div>
        <div class="calory-segmentos-dot" onclick="goToSegmentoSlide(1)"></div>
        <div class="calory-segmentos-dot" onclick="goToSegmentoSlide(2)"></div>
        <div class="calory-segmentos-dot" onclick="goToSegmentoSlide(3)"></div>
        <div class="calory-segmentos-dot" onclick="goToSegmentoSlide(4)"></div>
      </div>
    </div>
  </section>

  <!-- Por que escolher a Calory -->
  <section class="por-que-escolher">
    <h2>Por que escolher a Calory?</h2>
    <div class="por-que-container">
      <div class="por-que-item">
        <img src="https://cdn-icons-png.flaticon.com/256/5635/5635966.png" alt="Online">
        <h3>Adaptação 100%</h3>
        <p>O sistema se adapta ao seu negócio seja qual for ele.</p>
      </div>
      <div class="por-que-item">
        <img src="https://cdn-icons-png.flaticon.com/512/3064/3064197.png" alt="Seguro">
        <h3>Segurança de dados</h3>
        <p>Seus dados protegidos com backups automáticos.</p>
      </div>
      <div class="por-que-item">
        <img src="icon/logo-headset.svg" alt="Suporte">
        <h3>Suporte imediato</h3>
        <p>Atendimento direto via WhatsApp, sem enrolação.</p>
      </div>
    </div>
  </section>

  <!-- Seção de Vídeo Demonstração -->
  <section class="calory-video-section">
    <div class="container">
      <h2>Veja o CSEMP em ação</h2>
      <p>Assista a esta demonstração rápida e descubra como nosso sistema pode transformar a gestão do seu negócio:</p>
      
      <div class="calory-video-container">
        <iframe src="https://www.youtube.com/embed/Uw3EgubvVbo?rel=0&modestbranding=1" 
                frameborder="0" 
                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                allowfullscreen></iframe>
      </div>
      
      <a href="#form-orcamento" class="calory-cta-button" style="margin-top: 30px; background-color: #007bff;" onclick="expandFormSection()">
        <i class="fas fa-play-circle"></i> TESTE GRÁTIS POR 30 DIAS
      </a>
    </div>
  </section>

  <!-- Como funciona -->
  <section class="como-funciona">
    <h2>Como funciona?</h2>
    <div class="como-container">
      <div class="como-item">
        <img src="https://cdn-icons-png.flaticon.com/512/6815/6815042.png" alt="Passo 1">
        <h4>1. Simule</h4>
        <p>Clique no botão OBTER SISTEMA.</p>
      </div>
      <div class="como-item">
        <img src="https://cdn-icons-png.flaticon.com/512/1980/1980530.png" alt="Passo 2">
        <h4>2. Contrate</h4>
        <p>Contrate o sistema que atende a sua necessidade.</p>
      </div>
      <div class="como-item">
        <img src="https://cdn-icons-png.flaticon.com/512/4299/4299999.png" alt="Passo 3">
        <h4>3. Utilize</h4>
        <p>Tenha controle total da sua empresa.</p>
      </div>
    </div>
  </section>

  <div class="calory-whatsapp-float">
    <a href="https://api.whatsapp.com/send?phone=5544999939313&text=Olá,%20vim%20pelo%20site%20e%20gostaria%20de%20tirar%20algumas%20dúvidas!">
      <span class="calory-whatsapp-icon">
        <img src="icon/whats.png" alt="WhatsApp" />
      </span>
    </a>
  </div>

  <footer class="calory-footer">
    <div class="footer-container">
      <div class="footer-logo">
        <img src="calory branco png (1).png" alt="Calory Sistemas" class="footer-logo-img">
        <p>Soluções tecnológicas para as empresas. Inovação e qualidade para o seu negócio.</p>
      </div>
      
      <div class="footer-links">
        <h3>Links Rápidos</h3>
        <ul>
          <li><a href="/">Início</a></li>
          <li><a href="/produtos">Produtos</a></li>
          <li><a href="/contato">Contato</a></li>
        </ul>
      </div>      
      <div class="footer-links">
        <h3>Produtos</h3>
        <ul>
          <li><a href="/csemp">Sistema de Gestão</a></li>
          <li><a href="/estoque">Controle de Estoque</a></li>
          <li><a href="/csemite">Emissão de nota fiscal</a></li>
          <li><a href="/CertificadoDigital">Certificado Digital</a></li>
        </ul>
      </div>
      
      <div class="footer-social">
        <h3>Redes Sociais</h3>
        <div class="social-icons">
          <a href="https://www.facebook.com/calorysistemas/" target="_blank"><i class="fab fa-facebook-f"></i></a>
          <a href="https://www.instagram.com/calorysistemas/" target="_blank"><i class="fab fa-instagram"></i></a>
          <a href="https://www.linkedin.com/company/calory-sistema" target="_blank"><i class="fab fa-linkedin-in"></i></a>
        </div>
        <p style="margin-top: 20px;">Siga-nos para ficar por dentro das novidades!</p>
      </div>
    </div>
    
    <div class="footer-bottom">
      <p>&copy; 2023 Calory Sistemas. Todos os direitos reservados.</p>
    </div>
  </footer>

  <script>
  // Menu Mobile
  function caloryToggleMenu() {
    const menu = document.querySelector('.calory-nav-links');
    const toggle = document.querySelector('.calory-menu-toggle');
    menu.classList.toggle('active');
    toggle.classList.toggle('active');
  }

  // Carrossel de Banner
  let bannerCurrentSlide = 0;
  const bannerTrack = document.getElementById('bannerTrack');
  const bannerDots = document.querySelectorAll('.calory-banner-dot');
  const bannerTotalSlides = 3;
  let bannerInterval;
  let bannerIsAnimating = false;

  function goToBannerSlide(index, e) {
    if (e && e.cancelable) e.preventDefault();
    if (bannerIsAnimating) return;

    bannerIsAnimating = true;
    bannerCurrentSlide = index;
    bannerTrack.style.transform = `translateX(-${bannerCurrentSlide * 100}%)`;

    bannerDots.forEach((dot, i) => {
      dot.classList.toggle('active', i === bannerCurrentSlide);
    });

    setTimeout(() => {
      bannerIsAnimating = false;
    }, 500);
  }

  function nextBannerSlide(e) {
    if (e && e.cancelable) e.preventDefault();
    if (bannerIsAnimating) return;

    bannerCurrentSlide = (bannerCurrentSlide + 1) % bannerTotalSlides;
    goToBannerSlide(bannerCurrentSlide);
  }

  function prevBannerSlide(e) {
    if (e && e.cancelable) e.preventDefault();
    if (bannerIsAnimating) return;

    bannerCurrentSlide = (bannerCurrentSlide - 1 + bannerTotalSlides) % bannerTotalSlides;
    goToBannerSlide(bannerCurrentSlide);
  }

  // Carrossel de Segmentos - VERSÃO CORRIGIDA
  let segmentoCurrentSlide = 0;
  const segmentoTrack = document.getElementById('segmentosTrack');
  const segmentoDots = document.querySelectorAll('.calory-segmentos-dot');
  const segmentoTotalSlides = 5;
  const segmentoSlideWidth = 280 + 30; // Largura do card + margem
  let segmentoIsAnimating = false;

  function goToSegmentoSlide(index, e) {
    if (e && e.cancelable) e.preventDefault();
    if (segmentoIsAnimating) return;

    segmentoIsAnimating = true;
    segmentoCurrentSlide = index;
    
    // Calcula o deslocamento com base no slide atual
    const offset = -segmentoCurrentSlide * segmentoSlideWidth;
    segmentoTrack.style.transform = `translateX(${offset}px)`;

    // Atualiza os dots
    segmentoDots.forEach((dot, i) => {
      dot.classList.toggle('active', i === segmentoCurrentSlide);
    });

    setTimeout(() => {
      segmentoIsAnimating = false;
    }, 500);
  }

  function nextSegmentoSlide(e) {
    if (e && e.cancelable) e.preventDefault();
    if (segmentoIsAnimating) return;

    if (segmentoCurrentSlide < segmentoTotalSlides - 1) {
      segmentoCurrentSlide++;
      goToSegmentoSlide(segmentoCurrentSlide);
    }
  }

  function prevSegmentoSlide(e) {
    if (e && e.cancelable) e.preventDefault();
    if (segmentoIsAnimating) return;

    if (segmentoCurrentSlide > 0) {
      segmentoCurrentSlide--;
      goToSegmentoSlide(segmentoCurrentSlide);
    }
  }

  // Formulário
  function toggleFormSection() {
    const formSection = document.querySelector('.calory-form-section');
    formSection.classList.toggle('expanded');
    const toggleButton = document.querySelector('.calory-form-toggle');
    const isExpanded = formSection.classList.contains('expanded');

    toggleButton.innerHTML = isExpanded ? 'Minimizar Formulário <i class="fas fa-chevron-up"></i>' : 'Solicitar Teste Grátis <i class="fas fa-chevron-down"></i>';

    // Rola para o formulário quando expandido
    if (isExpanded) {
      setTimeout(() => {
        formSection.scrollIntoView({
          behavior: 'smooth',
          block: 'start'
        });
      }, 300);
    }
  }

  function expandFormSection() {
    const formSection = document.querySelector('.calory-form-section');
    if (!formSection.classList.contains('expanded')) {
      toggleFormSection();
    }
  }

  // Centralizar texto quando não houver imagem
  const slides = document.querySelectorAll('.calory-banner-slide');
  for (const slide of slides) {
    const img = slide.querySelector('img');
    const p = slide.querySelector('p');

    if (!img && p) {
      p.style.textAlign = 'center';
    }
  }

  // Inicialização
  document.addEventListener('DOMContentLoaded', function () {
    // Ativa primeiro slide do banner
    goToBannerSlide(0);
    bannerInterval = setInterval(nextBannerSlide, 25000);

    // Ativa primeiro slide dos segmentos
    goToSegmentoSlide(0);

    // Setas do carrossel de banner
    document.querySelectorAll('.calory-carousel-arrow').forEach(arrow=> {
      arrow.addEventListener('click', function (e) {
        if (e.cancelable) e.preventDefault();
        e.stopPropagation();
        if (this.classList.contains('left')) {
          prevBannerSlide(e);
        } else {
          nextBannerSlide(e);
        }
      });

      arrow.addEventListener('touchstart', function (e) {
        if (e.cancelable) e.preventDefault();
        e.stopPropagation();
        if (this.classList.contains('left')) {
          prevBannerSlide(e);
        } else {
          nextBannerSlide(e);
        }
      }, { passive: false });

      arrow.addEventListener('touchend', function (e) {
        e.stopPropagation();
      }, { passive: true });
    });

    // Menu mobile: fecha ao clicar nos links
    document.querySelectorAll('.calory-nav-links a').forEach(link => {
      link.addEventListener('click', () => {
        if (window.innerWidth <= 899.98) {
          caloryToggleMenu();
        }
      });
    });

    // Scroll suave para âncoras
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
      anchor.addEventListener('click', function (e) {
        e.preventDefault();
        document.querySelector(this.getAttribute('href')).scrollIntoView({
          behavior: 'smooth'
        });
      });
    });

    // Efeito de scroll no header
    window.addEventListener('scroll', function () {
      const header = document.querySelector('.calory-header');
      if (window.scrollY > 50) {
        header.classList.add('scrolled');
      } else {
        header.classList.remove('scrolled');
      }
    });

    // Validação do formulário
    document.getElementById('orcamentoForm').addEventListener('submit', function (e) {
      const tel = document.querySelector('input[name="telefone"]').value;
      if (!/^[0-9]{10,11}$/.test(tel)) {
        e.preventDefault();
        alert('Por favor, insira um WhatsApp válido com DDD');
        return false;
      }

      if (typeof gtag !== 'undefined') {
        gtag('event', 'form_submit', {
          'event_category': 'lead',
          'event_label': 'orcamento_form'
        });
      }
    });

    // Máscara para telefone
    document.querySelector('input[name="telefone"]').addEventListener('input', function () {
      this.value = this.value.replace(/\D/g, '').substring(0, 11);
    });

    // Drag no banner
    if (bannerTrack) {
      let bannerStartX = 0;
      let bannerIsDragging = false;

      bannerTrack.addEventListener('touchstart', e => {
        bannerStartX = e.touches[0].clientX;
        bannerIsDragging = true;
        clearInterval(bannerInterval);
      }, { passive: true });

      bannerTrack.addEventListener('touchmove', function (e) {
        if (bannerIsDragging && !bannerIsAnimating && e.cancelable) {
          const currentX = e.touches[0].clientX;
          const diff = bannerStartX - currentX;
          if (Math.abs(diff) > 50) {
            e.preventDefault();
            if (diff > 0) {
              nextBannerSlide(e);
            } else {
              prevBannerSlide(e);
            }
            bannerIsDragging = false;
          }
        }
      }, { passive: false });

      bannerTrack.addEventListener('touchend', () => {
        bannerIsDragging = false;
        bannerInterval = setInterval(nextBannerSlide, 25000);
      }, { passive: true });
    }

    // Drag no carrossel de segmentos
    if (segmentoTrack) {
      let segmentoStartX = 0;
      let segmentoIsDragging = false;

      segmentoTrack.addEventListener('touchstart', e => {
        segmentoStartX = e.touches[0].clientX;
        segmentoIsDragging = true;
      }, { passive: true });

      segmentoTrack.addEventListener('touchmove', function (e) {
        if (segmentoIsDragging && !segmentoIsAnimating && e.cancelable) {
          const currentX = e.touches[0].clientX;
          const diff = segmentoStartX - currentX;
          if (Math.abs(diff) > 50) {
            e.preventDefault();
            if (diff > 0) {
              nextSegmentoSlide(e);
            } else {
              prevSegmentoSlide(e);
            }
            segmentoIsDragging = false;
          }
        }
      }, { passive: false });

      segmentoTrack.addEventListener('touchend', () => {
        segmentoIsDragging = false;
      }, { passive: true });
    }
  });
</script>

</body>
</html>
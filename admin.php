<form method="POST" action="upload.php" enctype="multipart/form-data">
  <label>Selecione o tipo:</label><br>
  <select name="tipo" required>
    <option value="principal-1">Banner Principal - Banner 1 - Desktop</option>
    <option value="principal-1-mobile">Banner Principal - Banner 1 - Mobile</option>
    <option value="principal-2">Banner Principal - Banner 2 - Desktop</option>
    <option value="principal-2-mobile">Banner Principal - Banner 2 - Mobile</option>
    <option value="principal-3">Banner Principal - Banner 3 - Desktop</option>
    <option value="principal-3-mobile">Banner Principal - Banner 3 - Mobile</option>
  </select><br><br>

  <label>Imagem:</label><br>
  <input type="file" name="imagem" accept="image/*" required><br><br>

  <button type="submit">Enviar</button>
</form>
<section>
<?php 

if (is_array($this->get("errors")) and count($this->get("errors")) > 0)
{ 
  ?>
  <span style="color: red;">Identifiants non valides.</span>
  <?php
}
else
{
  if (!isset($_POST['cpt_pseudo']))
  {
    ?>
    <form style="margin: auto; margin-top: 50px; width: 400px; text-align: center;" id="inscription_form" method="post" action="?connect">
      <label for="cpt_pseudo">Identifiant :</label>
      <input type="text" name="cpt_pseudo" id="cpt_pseudo" value="<?php echo $this->get("cpt_pseudo")?>" /> <br />

      <label for="cpt_password">Mot de passe :</label>
      <input type="password" name="cpt_password" id="cpt_password" value="<?php echo $this->get("cpt_password")?>" /> <br />
      <input type="submit" value="Se connecter" />
    </form>
    <?php
  }
  else
  {
    session_start();
    $_SESSION["cpt_pseudo"] = $this->get("cpt_pseudo");
    header("Location: ?user=" . $this->get("cpt_pseudo"));
  }
}

?>
</section>
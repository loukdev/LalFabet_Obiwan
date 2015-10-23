<?php
include_once('api/IView.class.php');

class ViewSignIn implements IView
{
	private $model = NULL;

	public function __construct($model)
	{
		$this->model = $model;
	}

	public function show()
	{
		ob_start();

		include_once("templates/begin.php");
		include_once("templates/header.php");

		echo '<section>';

		if (count($this->model->getInfos()['errors']))
		{
			foreach ($this->model->getInfos()['errors'] as $err)
			{
				echo '<span style="color: red;">' . $err . '</span><br/>';
			}

			include_once('templates/signin_form.php');
		}
		else
		{
			include_once('templates/signin_success.php');
		}

		echo '</section>';

		include_once("templates/footer.php");
		include_once("templates/end.php");

		ob_end_flush();
	}

	private function get($key)
	{
		if (isset($this->model->getInfos()[$key])) 
			return $this->model->getInfos()[$key];
		else
			return '';
	}
}

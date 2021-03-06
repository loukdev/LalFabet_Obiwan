﻿<?php

require_once('api/IModel.class.php');
require_once('api/Model.class.php');
require_once('api/Obiwan.class.php');
require_once('api/utility.php');

require_once('models/ModelSubscription.class.php');

define('TABLE_NAME_ADH', '`t_adherent_adh`');
define('TABLE_NAME_CPT', '`t_compte_cpt`');

/*!
 * \class ModelUser
 * \brief Modèle représentant les tables t_adherent_adh et t_compte_cpt.
 * 
 *  Ce modèle représente les données relatives aux tables t_adherent_adh
 * et t_compte_cpt.
 * 
 *  Il permet :
 *   - la création d'un adhérent et de son compte utilisateur.
 *   - la récupération d'un adhérent et de son compte utilisateur ;
 * 
 * \todo Suppression d'un utilisateur dans la base de données.
 * \todo Déléguer le code de vérification d'abonnement et d'appartenance à un
 * groupe aux futurs modèles ModelGroup et ModelSubscription.
 */
class ModelUser extends Model implements IModel
{
	private $query_results = array();	//!< Contient le résultat de la dernière requête SQL effectuée.

	private static $add_adh_query = 'INSERT INTO'. TABLE_NAME_ADH .'
				( `cpt_pseudo`
				, `adh_nom`
				, `adh_prenom`
				, `adh_date_naissance`
				, `adh_rue`
				, `adh_code_postal`
				, `adh_ville`
				, `adh_telephone1`
				, `adh_telephone2`
				, `adh_telephone3`
				, `adh_mail`
				, `adh_num_rue`
				) VALUES
			  ( :cpt_pseudo
				, :adh_nom
				, :adh_prenom
				, :adh_date_naissance
				, :adh_rue
				, :adh_code_postal
				, :adh_ville
				, :adh_telephone1
				, :adh_telephone2
				, :adh_telephone3
				, :adh_mail
				, :adh_num_rue
				)';

	private static $add_cpt_query = 'INSERT INTO'. TABLE_NAME_CPT .'
				( `cpt_pseudo`
				, `cpt_password`
				) VALUES
				( :cpt_pseudo
				, :cpt_password
				)';
	private static $del_adh_query =
			'DELETE FROM '. TABLE_NAME_ADH .' WHERE adh_id=';
	private static $del_cpt_query =
			'DELETE FROM '. TABLE_NAME_CPT .' WHERE adh_id=';
	private static $get_usr_query =
			'SELECT * FROM '. TABLE_NAME_ADH .' NATURAL JOIN '.
							  TABLE_NAME_CPT .' WHERE cpt_pseudo=';

	/*!
	 * \brief Constructeur, remplie les données contenues dans $array.
	 * \param $array Liste des champs contenant les données.
	 * 
	 *  Remplie les données du modèle en s'assurant que tous les champs de la
	 * table TABLE_NAME_ACT sont présents.
	 */
	public function __construct($array)
	{
		$this->data = array_merge(array(
			  'cpt_pseudo' => ''
			, 'cpt_password' => ''
			, 'cpt_password_verif' => ''
			, 'adh_prenom' => ''
			, 'adh_nom' => ''
			, 'adh_date_naissance' => ''
			, 'adh_rue' => ''
			, 'adh_num_rue' => ''
			, 'adh_code_postal' => ''
			, 'adh_ville' => ''
			, 'adh_telephone1' => ''
			, 'adh_telephone2' => ''
			, 'adh_telephone3' => ''
			, 'adh_mail' => ''), $array);
	}

	/*!
	 * \brief Renvoie le modèle de l'utilisateur correspondant au pseudo envoyé.
	 * \param $username Pseudo de l'utilisateur à récupérer.
	 * 
	 *  Récupère un compte utilisateur et ses données adhérent correspondant au
	 * pseudo $username.
	 *  Après exécution, il est important de vérifier qu'une erreur n'a pas été
	 * détectée grâce à hasErrors().
	 */
	public static function getUser($username)
	{
		return ModelUser::getUserPrivate($username, true);
	}

	public static function exists($username)
	{
		$db = Obiwan::PDO();
		try {
			$db->query(self::$get_usr_query . $username);
		} catch (Exception $ex) {
			return false;
		}

		return $db ? true : false;
	}

	/*!
	 * \brief Récupère un utilisateur.
	 * \param $username Pseudo de l'utilisateur à récupérer.
	 * \param $check Booléen indiquant s'il faut vérifier l'utilisateur.
	 * \return Le modèle de l'utilisateur à visionner.
	 * 
	 *  Récupère l'utilisateur correspondant au pseudo $username. Si $check
	 * vaut true, une comparaison entre l'utilisateur en question et celui
	 * enregistré dans la session (si quelqu'un est connectée) est effectuée.
	 *  Si les pseudos correspondent (l'utilisateur veut voir ses propres
	 * données), les données sont récupérées à condition que l'abonnement soit
	 * à jour.
	 *  Sinon, si l'utilisateur souhaitant visionner ne possède pas les droits
	 * nécessaires pour connaître les données de l'utilisateur à récupérer
	 * (animateurs : peut gérer les petits bidouilleurs ; gestionnaires : tous
	 * les droits ; admin : tous les droits), une erreur est produite.
	 *
	 * Pour vérifier la présence d'erreurs, voir hasErrors() et getErrors().
	 */
	private static function getUserPrivate($username, $check)
	{
		$ret = new ModelUser(array());
		// si verif, alors verifier le droit d'acceder a ce compte

		if($check)
		{
			if(!isset($_SESSION['cpt_pseudo']))
			{
				$ret->addError("Vous n'êtes pas connecté.");
				return $ret;
			}

			// Pas d'abonnement, pas de droit (même pas celui de regarder son profil).
			if(empty($_SESSION['grp_id']))
			{
				$ret->addError("Votre abonnement n'est pas à jour.");
				return $ret;
			}

			if($_SESSION['cpt_pseudo'] != $username)
			{
				// On récupère les données de l'utilisateur visualisé.
				$other = self::getUserPrivate($username, false);

				// S'il y a eu une erreur (l'utilisateur n'existe pas, par exemple), on s'arrête.
				if($other->hasErrors())
				{
					$ret->errors = $other->errors;
					return $ret;
				}

				// On tente de récupérer les droits de l'utilisateur connecté.
				try {
					$user_sub = ModelGroup::get($_SESSION['grp_id']);
				} catch (Exception $ex) {
					$ret->addError($ex->getMessage());
					return $ret;
				}

				// On tente de récupérer l'abonnement de l'utilisateur visualisé.
				try {
					ModelSubscription::getFromUser($other->cpt_pseudo);
				} catch (Exception $ex) {
				// Si l'utilisateur visualisé n'a pas encore d'abonnement, l'utilisateur connecté
				// doit avoir un niveau suffisant pour le visualier (de même que pour le valider).
					if($user_sub->grp_niveau < 3)
					{
						$ret->addError("Vous n'avez pas les droits suffisants pour visualiser les personnes sans abonnement.");
						return $ret;
					}
				}

				// On vérifie que l'utilisateur connecté à les droits nécessaires.
				if(!$other->isMinor() && $user_sub->grp_acces_autre != 1 ||
					$other->isMinor() && $user_sub->grp_acces_petit != 1)
				{
					$ret->addError("Vous n'avez pas les droits nécessaires pour visualiser ce membre.");
					return $ret;
				}

			}
		}

		try
		{
			$db = Obiwan::PDO();

			$q = $db->query(self::$get_usr_query . "'$username'");

			if(!$q or $q->rowCount() <= 0)
				throw new Exception($db->errorInfo()[2]);
			else
				$ret->query_results = $q;
		}
		catch (Exception $e) {
			if(!empty($e->getMessage()))
				$ret->addError('Une erreur serveur est survenue. '. $e->getMessage());
		}

		if (!$ret->query_results)
		{
			$ret->addError("Aucun utilisateur n'a pour nom $username.");
		}
		else
		{
			$arr = $ret->query_results->fetchAll();
			$ret->data = $arr[0];
		}

		return $ret;
	}

	public function isMinor()
	{
		$date = strtotime(str_replace('/', '-', $this->data['adh_date_naissance']));
		$diff = time() - $date;

		if(!$date)
			return false;
		else
			return $diff < 18 * 356 * 24 * 3600;
	}

	/*!
	 * \brief Enregistre un adhérent et son compte utilisateur.
	 * \param $array Liste des champs.
	 * 
	 *  Enregistre un adhérent et son compte utilisateur avec les données contenu
	 * dans $array, qui doit être indexé de la même manière que dans les tables.
	 * 
	 *  Ne lance pas d'exception : s'il y a une erreur, elle est ajoutée à la
	 * liste interne d'erreurs. La présence d'erreur peut être vérifiée grâce à
	 * hasErrors() et elles peuvent être récupérées via getErrors().
	 */
	public function tryAddAccount($array)
	{
		$this->data = $array;

		// Verification de la présence des informations reçues
		if (are_all_set($this->data, array(
			  'cpt_pseudo'
			, 'cpt_password'
			, 'cpt_password_verif'
			, 'adh_prenom'
			, 'adh_nom'
			, 'adh_date_naissance'
			, 'adh_rue'
			, 'adh_num_rue'
			, 'adh_code_postal'
			, 'adh_ville'
			, 'adh_telephone1'
			, 'adh_telephone2'
			, 'adh_telephone3'
			, 'adh_mail'
		)))
		{
			$this->tryAddAccountPrivate();
		}
		else
		{
			$this->addError('');
		}
	}

	/*!
	 * \brief Enregistre un compte.
	 * 
	 *  Exécute "le sale boulot" pour ajouter un compte : vérification de la
	 * validité de toutes les données du modèle, requêtes SQL etc...
	 * 
	 *  Ne lance pas d'exception (voir tryAddAccount()).
	 */
	private function tryAddAccountPrivate()
	{
		if (strlen($this->data['cpt_pseudo']) < 1
			or strlen($this->data['cpt_password']) < 1
			or strlen($this->data['cpt_password_verif']) < 1
			or strlen($this->data['adh_prenom']) < 1
			or strlen($this->data['adh_nom']) < 1
			or strlen($this->data['adh_date_naissance']) < 1
			or strlen($this->data['adh_rue']) < 1
			or strlen($this->data['adh_code_postal']) < 1
			or strlen($this->data['adh_ville']) < 1
			or strlen($this->data['adh_telephone1']) < 1
			or strlen($this->data['adh_mail']) < 1)
		{
			$this->addError('Tous les champs avec une étoile sont à renseigner.');
		}

		// verification mot de passe
		if ($this->data['cpt_password'] != $this->data['cpt_password_verif'])
		{
			$this->addError('Les deux mots de passe ne correspondent pas.');
		}

		// verification numéros de téléphone
		$nombre_num_valides = 0;
		if (strlen($this->data['adh_telephone1']) > 0)
		{
			if (strlen($this->data['adh_telephone1']) != 10) {
				$this->addError('Téléphone 1 invalide.');
			} else {
				$nombre_num_valides++; }
		}
		if (strlen($this->data['adh_telephone2']) > 0)
		{
			if (strlen($this->data['adh_telephone2']) != 10) {
				$this->addError('Téléphone 2 invalide.');
			} else {
				$nombre_num_valides++; }
		}
		if (strlen($this->data['adh_telephone3']) > 0)
		{
			if (strlen($this->data['adh_telephone3']) != 10) {
				$this->addError('Téléphone 3 invalide.');
			} else {
				$nombre_num_valides++; }
		}

		$time = strtotime(str_replace('/', '-', $this->data['adh_date_naissance']));
		// verification date de naissance
		if ($time != false and time() - $time > 0)
		{
			if ($this->isMinor() && $nombre_num_valides < 2)
				$this->addError('Si vous avez moins de 18 ans, vous avez besoin de deux numéros de téléphone.');
			else
				$this->data['adh_date_naissance'] = date('Y-m-d', $time);
		}
		else
			$this->addError('Date invalide.');
    
		// vérification adresse mail
		if (!preg_match('/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $this->data['adh_mail']))
		{
			$this->addError('Adresse mail invalide.');
		}

		// code postal
		if (strlen($this->data['adh_code_postal']) != 5)
		{
			$this->addError('Code postal invalie');
		}

		if (count($this->errors) > 0)
		{
			return;
		}

		$db = NULL;
		try
		{
			// récupération de la bdd et début de transaction
			$db = Obiwan::PDO();
			$db->beginTransaction();

			// ajout d'une entrée à t_compte_cpt
			$cpt = $db->prepare(self::$add_cpt_query);
			if (!$cpt->execute(array( 'cpt_pseudo' => $this->data['cpt_pseudo']
									, 'cpt_password' =>  $this->data['cpt_password'])))
			{
				$err = $cpt->errorInfo();
				throw new Exception("Une erreur serveur est survenue. Le nom d'utilisateur existe peut-être déjà.");
			}
			// ajout à t_adherent_adh
			$adh = $db->prepare(self::$add_adh_query);
			if (!$adh->execute(
				poor_array_diff_key($this->data, array('cpt_password' => ''
														   , 'cpt_password_verif' => ''))
				))
			{
				$err = $adh->errorInfo();
				throw new Exception('Une erreur serveur est survenue. ' . $err[2]);
			}

			// transaction terminée sans erreurs
			$db->commit();
		}
		catch (Exception $e)
		{
			// si jamais le problème provient d'une requête, on rollback
			if (!is_null($db))
			{
				$db->rollBack();
			}

			$this->addError($e->getMessage());
		}
	}

	public function save()
	{
		$this->tryAddAccountPrivate();
	}

	public function delete()
	{
		$db = null;
		try {
			$db = Obiwan::PDO();
			$db->beginTransaction();

			$adh = $db->query(self::$del_adh_query);
			if(!$adh)
			{
				$err = $db->errorInfo();
				throw new Exception('Une erreur serveur est survenue. '. $err[2]);
			}

			$cpt = $db->query(self::$del_cpt_query);
			if(!$cpt)
			{
				$err = $db->errorInfo();
				throw new Exception('Une erreur serveur est survenue. '. $err[2]);
			}

			$db->commit();
		} catch (Exception $e) {
			if(!is_null($db))
				$db->rollBack();

			$this->addError($e->getMessage());
		}
	}

	public static function getAll()
	{
		try
		{
			$result = array();
			$db = Obiwan::PDO();
			$q  = $db->query('SELECT * FROM '. TABLE_NAME_CPT);
			if(!$q)
				throw new Exception(__CLASS__ . '::getAll : select cpt query failed.');
			else
				$result = array_merge($result, $q->fetchAll());

			$q  = $db->query('SELECT * FROM '. TABLE_NAME_ADH);
			if(!$q)
				throw new Exception(__CLASS__ . '::getAll : select adh query failed.');
			else
				return array_merge($result, $q->fetchAll());
		}
		catch (Exception $_)
		{
			return array();
		}
	}

	/*!
	 * \brief Renvoie les données du modèle.
	 * \return Un array.
	 */
	public function getInfos()
	{
		return $this->data;
	}

	/*!
	 * \brief Renvoie les résultats de la dernière requête effectuée.
	 * \return Un array.
	 */
	public function getRows()
	{
		return $this->query_results;
	}

	/*!
	 * \brief Inscrit un utilisateur avec $array pour données.
	 * \param $array Données de l'utilisateur à inscrire.
	 * 
	 *  Crée un nouvel adhérent et son compte utilisateur associé.
	 *  Après exécution, il est important de vérifier qu'une erreur n'a pas été
	 * détectée grâce à hasErrors().
	 */
	public static function signIn($array)
	{
		$ret = new ModelUser($array);
		$ret->tryAddAccount($array);

		return $ret;
	}

	/*!
	 * \brief Tente la connexion de l'utilisateur envoyé en paramètre.
	 * \param $array Array contenant les données de l'utilisateur.
	 * 
	 *  Tente la connexion de l'utilisateur. Récupère d'abord les données de
	 * l'utilisateur grâce à getUserPrivate(), puis vérifie s'il n'y a pas
	 * d'erreur. Ensuite, s'assure que les mots de passe correspondent puis
	 * renvoie l'utilisateur en question, avec l'id de son groupe si
	 * l'abonnement est à jour.
	 */
	public static function tryConnect($array)
	{
		// On récupère l'utilisateur.
		$ret = ModelUser::getUserPrivate($array['cpt_pseudo'], false);

		// S'il y a des erreurs ou que les mots de passe de concordent pas,
		//  on stoppe la fonction en renvoyant le modèle contenant les erreurs.
		if($ret->hasErrors())
			return $ret;
		else if($array['cpt_password'] != $ret->data['cpt_password'])
		{
			$ret->addError('Mauvais mot de passe.');
			return $ret;
		}

		// On récupère l'abonnement de l'utilisateur.
		try {
			$subs = ModelSubscription::getFromUser($ret->data['cpt_pseudo']);
		} catch (Exception $ex) {
		// S'il y a une exception, c'est qu'il n'en a probablement pas.
		// On stoppe la fonction en renvoyant le modèle contenant les erreurs.
			$ret->addError($ex->getMessage());
			return $ret;
		}

		// Si l'utilisateur a un abonnement, il appartient à un groupe. On
		//  ajoute l'id du groupe dans la session.
		$_SESSION['grp_id'] = $subs->grp_id;

		return $ret;
	}
}

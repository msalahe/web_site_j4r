<?php
//Id du user depot de J4R pour interrogation graph explorer
$UserId = "64b4f5f5-6741-4d7c-873c-0cdc64eff509";
//Id du calendrier transports J4R
$CalendarId = "AAMkADU3MDRkNmI4LTM1NTUtNGNmMS1hMzEwLTBjOTYxMjU2M2ViMwAuAAAAAAAhabwJHj2dQbJDtMwJcRmzAQAK7FnT_2tgTaAsVzxSi0cLAACc8k4LAAA="; //Id du calendrier Transport
//serveur de mails crmj4r
$server = '{mail.crmj4r.fr:110/pop3/notls}INBOX';
//username boite aux lettre depot@crmj4r.fr
$username = 'test@crmj4r.fr';
//password boite aux lettre depot@crmj4r.fr
$password = 'J4r77400!';

//Fonction qui permet de detecter le code chantier s'il a été saisi par le conducteur dans l'adresse
function detecterChantier($chaine) {
  // Expression régulière pour un nombre à 5 ou 6 chiffres commençant par 2
  $regex = '/\b2\d{5,6}\b/';

  // On utilise preg_match pour rechercher la correspondance dans la chaîne
  if (preg_match($regex, $chaine, $matches)) {
    // On retourne le nombre entier
    return (int) $matches[0];
  } else {
    // On retourne null si aucun nombre n'est trouvé
    return null;
  }
}

//fonction qui corrige la date d'enlevemnt si erreur de saisie avec date inferieure à la date commande
function VerifDateEnlev($DateEnlevInv) {	
		//On repasse en format Date
		$FlagAn = true;
		$FlagMois = true;
		$FlagJour = true;
		$DateDuJour = date("Y-m-d");
		$DateDuJourInv = substr($DateDuJour,0,4).substr($DateDuJour,5,2).substr($DateDuJour,8,2);
		if ($DateEnlevInv<$DateDuJourInv) {	//impossible la demande de livraison ne peut etre inférieure à la date du jour			
			if (substr($DateEnlevInv,0,4) < substr($DateDuJour,0,4)) {	//annee inferieure
				$FlagAn = False;
			}
			if (substr($DateEnlevInv,5,2) < substr($DateDuJour,5,2)) {	//mois inferieur
				if ($FlagAn = true) { //pas d'erreur dans  l'année alors le mois ne peut pas etre inferieur
					$FlagMois = False;
				}
			}
			if (!$FlagAn) {
				$DateEnlevInv = substr($DateDuJourInv,0,4).substr($DateEnlevInv,4,2).substr($DateEnlevInv,6,2);
			}
			if (!$FlagMois) {
				$DateEnlevInv = substr($DateEnlevInv,0,4).substr($DateDuJourInv,4,2).substr($DateEnlevInv,6,2);
			}
		}
		return $DateEnlevInv;
}

function getAddressInfo($data) {
    return [
        'NomClient' => $data->m_clAdresse->m_sNom_client,
        'AdresseChantier' => $data->m_clAdresse->m_sAdresse_1 . " " . $data->m_clAdresse->m_sAdresse_2 . " " . $data->m_clAdresse->m_sAdresse_3,
        'CpChantier' => $data->m_clAdresse->m_sCode_postal,
        'VilleChantier' => $data->m_clAdresse->m_sVille,
        'NumAffaire' => $data->m_clAdresse->m_sInstructionsSupplementaires,
        'DateCdeDeb' => $data->m_clAdresse->m_stInformations->dDate_decompte,
        'NumEchange' => $data->m_clAdresse->m_stInformations->sNumero_commande,
        'Initiales' => $data->m_clAdresse->m_stInformations->sInitiales,
        'CodeChantier' => $data->m_clAdresse->m_stInformations->sCode_Analytique,
        'NumDevis' => $data->m_clAdresse->m_stInformations->sNumeroDevis,
        'IdClient' => $data->m_clAdresse->m_stInformations->sNumero_plan,
        'Expediteur' => $data->m_clAdresse->m_stInformations->sExpediteur,
        'DateEnlevDem' => $data->m_clAdresse->m_stInformations->dDate_enlevement,
        'HeureEnlevDem' => substr($data->m_clAdresse->m_stInformations->hHeure_enlevement, 0, 5),		
        'NumAgenceLayer' => $data->m_clAdresse->m_stInformations->sAgence_enlevement,
        'Commentaires' => $data->m_clAdresse->m_stInformations->tabCommentaire,
        'PoidTotMat' => $data->xPoidsTotal,
    ];
}

function getNumAffaireId($pdo, $NumAffaire) {
    $resultNumAffaire = $pdo->query("SELECT * FROM etatsencours WHERE NumAffaire = '" . $NumAffaire . "'");
    return ($resultNumAffaire->rowCount() == 0) ? 0 : $resultNumAffaire->fetch()['id'];
}

function getConducId($pdo, $Initiales, $emailConduc) {
    $resultCC = $pdo->query("SELECT * FROM user WHERE UPPER(pseudo) = UPPER('" . $Initiales . "')");
    $row = $resultCC->fetch();
    if ($row) {
        return $row['id'];
    } else {
        $resultCC = $pdo->query("SELECT * FROM user WHERE email = '" . $emailConduc . "'");
        $row = $resultCC->fetch();
        return $row ? $row['id'] : 0;
    }
}

function getAgenceId($pdo, $IdAgence) {
    $result = $pdo->query('SELECT * FROM agence WHERE Agence = ' . $IdAgence);
    return ($result->rowCount() == 0) ? 4 : trim($result->fetch()['IDAgence']);
}

function getDepotId($pdo, $IdDepot) {
    $result = $pdo->query("SELECT * FROM depot WHERE CodeDepot = " . $IdDepot);
    return ($result->rowCount() == 0) ? 11 : trim($result->fetch()['IDDepot']);
}

// Fonction pour obtenir l'ID de la nouvelle ligne
function getIdCdeMatEnt($pdo, $estVide, $NumId) {
    return $estVide ? $pdo->lastInsertId() : $NumId;
}

// Fonction d'insertion ou mise à jour des articles
function insertOrUpdateArticles($pdo, $articles, $TypeMat, $IdCdeMatEnt, $estVide) {
    $PoidsTotMat = 0;
    foreach ($articles as $article) {
        $CodeArticle = $article->sCodeArticle;
        $Designation = $article->sDesignation;
        $Quantite = $article->nQuantite;
        $Poids = $article->xPoids;
        $PoidsTotMat += $Poids * $Quantite;

        if ($estVide) {
            $query = 'INSERT INTO cde_mat_det(article, designation, qte, poids, type_mat, id_cde_mat_ent) VALUES (:CodeArticle, :Designation, :Quantite, :Poids, :TypeMat, :IdCdeMatEnt)';
        } else {
            $resultatArt = $pdo->query("SELECT * FROM cde_mat_det WHERE id_cde_mat_ent = $IdCdeMatEnt AND type_mat = '$TypeMat' AND article = '$CodeArticle'");
            $estVide2 = ($resultatArt->rowCount() == 0);
            $query = $estVide2
                ? 'INSERT INTO cde_mat_det(article, designation, qte, poids, type_mat, id_cde_mat_ent) VALUES (:CodeArticle, :Designation, :Quantite, :Poids, :TypeMat, :IdCdeMatEnt)'
                : 'UPDATE cde_mat_det SET article = :CodeArticle, designation = :Designation, qte = :Quantite, poids = :Poids, type_mat = :TypeMat WHERE id_cde_mat_ent = :IdCdeMatEnt AND type_mat = :TypeMat AND article = :CodeArticle';
        }

        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':CodeArticle', $CodeArticle);
        $stmt->bindParam(':Designation', $Designation);
        $stmt->bindParam(':Quantite', $Quantite);
        $stmt->bindParam(':Poids', $Poids);
        $stmt->bindParam(':TypeMat', $TypeMat);
        $stmt->bindParam(':IdCdeMatEnt', $IdCdeMatEnt);
        $stmt->execute();
    }
    return $PoidsTotMat;
}

// Fonction d'insertion ou mise à jour du transport
function insertOrUpdateTransport($pdo, $IdCdeMatEnt, $PoidsTotMat, $DateCde, $DateEnlevDem, $eventId, $Etat, $IdChant, $IdNumAffaire, $HeureDep) {
    $resultTrans = $pdo->query("SELECT * FROM transports WHERE idcde = $IdCdeMatEnt AND type_transport = 1 AND numchantierarrive = $IdChant");
    $estVideTrans = ($resultTrans->rowCount() == 0);
    if ($estVideTrans) {
        $query = "INSERT INTO transports (idcde, type_transport, heuredepart, heuredep2, numchantierdepart, numchantierarrive, poidsbon, volume, poidsbalance, envoifdr, datesaisie, taux_prefere, montant, type_enlevement, id_num_affaire, creation_affectation, date_transport, date_livraison, event_transport_id, etat) VALUES ($IdCdeMatEnt, 1, '".substr($HeureDep, 0, 5)."', '".substr($HeureDep, 0, 5)."', 883, $IdChant, $PoidsTotMat, 0, 0, 0, '".substr($DateCde, 0, 10)."', 0, 0, 0, $IdNumAffaire, 0, '$DateEnlevDem', '$DateEnlevDem', '$eventId', $Etat)";
    } else {
        $query = "UPDATE transports SET heuredepart = '".substr($HeureDep, 0, 5)."', heuredep2 = '".substr($HeureDep, 0, 5)."', numchantierdepart = 883, numchantierarrive = $IdChant, poidsbon = $PoidsTotMat, datesaisie = '".substr($DateCde, 0, 10)."', id_num_affaire = $IdNumAffaire, date_transport = '$DateEnlevDem', date_livraison = '$DateEnlevDem', event_transport_id = '$eventId', etat = $Etat WHERE idcde = $IdCdeMatEnt AND type_transport = 1 AND numchantierarrive = $IdChant";
    }
    $pdo->query($query);
}

// Fonction pour enregistrer l'erreur dans la table temporaire
function enregistrerErreur($pdo, $expediteurMail, $objet, $missing_CodeChantier, $missing_NumAffaire, $missing_Date_Enlevement, $missing_Heure_Enlevement, $IdConduc) {
    $stmt = $pdo->prepare(
        "INSERT INTO commande_windec_erreur 
        (expediteur_mail, objet, code_chantier_manquant, num_affaire_manquant, date_enlevement_manquant, heure_enlevement_manquant, user_id) 
        VALUES 
        (:expediteur, :objet, :code_chantier_manquant, :num_affaire_manquant, :date_enlevement_manquant, :heure_enlevement_manquant, :user_id)"
    );
    $stmt->execute([
        ':expediteur' => $expediteurMail,
        ':objet' => $objet,
        ':code_chantier_manquant' => $missing_CodeChantier,
        ':num_affaire_manquant' => $missing_NumAffaire,
        ':date_enlevement_manquant' => $missing_Date_Enlevement,
        ':heure_enlevement_manquant' => $missing_Heure_Enlevement,
        ':user_id' => $IdConduc
    ]);
}

?>
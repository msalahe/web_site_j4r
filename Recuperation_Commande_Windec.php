<?php
// Inclure les librairies
require_once __DIR__ . '/defaultFunctionCronCommande.php';
require_once __DIR__ . '/../defaultFunctionCron.php';
//Autoload de Dev
require_once __DIR__ . '/../../httpdocsdev/vendor/autoload.php';
//Autoload de Prod
//require_once __DIR__ . '/../../httpdocs/vendor/autoload.php';

// Connexion à la base de données de dev,prod ou test
$pdo = connectToDatabase('dev');



//définitions Variables
date_default_timezone_set('Europe/Paris');
//Fichier Log
$fichierLog = __DIR__ . "/LogMail.txt";
//$Patch pour requete calendrier outlook
$Patch = "Faux";
//$Actif pour indiquer que la commande est active et pas archivée (car si $actif = 0 alors commandes archivées avec motif)
$Actif = True;
//liste des expediteurs autorisés
$expediteurs_autorises = ["ne_pas_repondre@layher.fr", "bernard@crmj4r.fr", "depot@crmj4r.fr"];
//ouverture de la boite aux lettres
$mailbox = imap_open($server, $username, $password);
$mails = FALSE;

//Recuperation Token Access en BDD
$resultat = $pdo->query("Select * from ParamAgence where IdAgence = 4");
$estVide2 = ($resultat->rowCount() == 0);
if (!$estVide2) {
	$RecupToken = $resultat->fetch();
	$AccessToken = $RecupToken['AccessToken'];	
	//si recuperation token, on peut traiter le mail
	// Traitement du mail
	if (FALSE === $mailbox) {
		writeLog("La connexion au serveur de mail a échoué. récupération commande impossible",$fichierLog,'ALERT');
	} else {
		$NbMess = imap_num_msg($mailbox);	
		echo "nomber message ". $NbMess;
		if ($NbMess > 0) { //Nb Mails en file d'attente
			$mails = imap_fetch_overview($mailbox, '1:'.$NbMess, 0);
			foreach ($mails as $mail) { //On traite les mails 1 par 1
				//remise a zero des variables pour chaque mail
				//initialisation des variables de controles des infos manquantes
				$missing_CodeChantier = false;
				$missing_NumAffaire = false;
				$missing_Date_Enlevement = false;
				$missing_Heure_Enlevement = false;
				$NumCommande = 0;
				$NumId = 0;
				$Etat = 1; // Demande Cdt puisque cde Windec				
				$CodeChantierTemp3 = '';
				$DateEnlevDem2 = '';
				$HeureEnlevDem2 = '';
				$IdAgence2 = '';
				$IdDepot2 = '';
				$NumAffaire2 = '';				
				// Récupération des informations de l'en-tête du mail
				$header = imap_headerinfo($mailbox, $mail->msgno);
				// Vérification si la récupération de l'en-tête a réussi
				if ($header !== false && isset($header->from) && is_array($header->from) && count($header->from) > 0) {
					// Vérification si les informations de l'expéditeur sont disponibles
					if (isset($header->from[0]->mailbox) && isset($header->from[0]->host)) {
						$expediteurMail = $header->from[0]->mailbox . '@' . $header->from[0]->host;
					} else {
						$expediteurMail = null;
					}
				} else {
					// Gestion du cas où la récupération de l'en-tête a échoué
					$expediteurMail = null;
				}
				$objet = (iconv_mime_decode($mail->subject,0, "ISO-8859-1"));
				$dateHeureMail = strtotime($mail->date); // Récupère la date et l'heure du mail au format timestamp Unix
				// Convertir le timestamp Unix en date et heure lisible
				$dateHeureLisible = date("Y-m-d H:i:s", $dateHeureMail);		
				// Extraction des informations du sujet de l'email
				$Position = strpos($objet,"Commande"); //pour numero d'echange
				$PosPar1 = strpos($objet,"("); //pour debut mail conducteur
				$PosPar2 = strpos($objet,")"); //pour fin mail conducteur
				$PosPar3 = strpos($objet,"/"); //pour code chantier sur 5 digits
				$PosPar4 = strpos($objet,"?"); //pour Date Enlevement souhaite sous forme "2024-04-24"
				$PosPar5 = strpos($objet,"!"); //pour heure Enlevement souhaite sous forme "10:25"
				$PosPar6 = strpos($objet,"$"); //pour Numero d'agence (toujours 1 pour le moment)
				$PosPar7 = strpos($objet,"*"); //pour Numero de depot (pour le moment = 1 si Layher, 2 si Lagny)
				$PosPar8 = strpos($objet,"+"); //pour Numero d'affaire Layher
				if ($Position !== false) {
					$NumCommande = intval(substr($objet,$Position+9,3) . substr($objet,$Position+13,3) . substr($objet,$Position+17,3));
					writeLog("Traitement du mail : ".$objet,$fichierLog); //Info par default dans Log
					if ($PosPar1 !== false And $PosPar2 !== false) {
						$TailleChaine = $PosPar2 - $PosPar1;
						$emailConduc = substr($objet,$PosPar1+1,$TailleChaine - 1);
					} else {
						$emailConduc = "";
					}
					if ($PosPar3 !== false) {
						$CodeChantierTemp3 = substr($objet,$PosPar3+1,5);
					}
					if ($PosPar4 !== false) {
						$DateEnlevDem2 = substr($objet,$PosPar4+1,10);
					}
					if ($PosPar5 !== false) {
						$HeureEnlevDem2 = substr($objet,$PosPar5+1,5);
					}
					if ($PosPar6 !== false) {
						$IdAgence2 = substr($objet,$PosPar6+1,1);
					}
					if ($PosPar7 !== false) {
						$IdDepot2 = substr($objet,$PosPar7+1,1);
					}
					if ($PosPar8 !== false) {
						$NumAffaire2 = substr($objet,$PosPar8+1,7);
					}				
					//Traitement du mail
					$resultat = $pdo->query("Select * from cde_mat_ent where num_echange = ".$NumCommande);				
					$estVide = ($resultat->rowCount() == 0);
					if ($estVide) {
						// Préparation de la requête d'insertion
						$eventId = 0;
						$query = 'INSERT INTO cde_mat_ent(num_affaire,date_cde,poids_tot_mat,date_enlev_dem,heure_enlev_dem,date_enlev_reel,heure_enlev_reel,date_liv,heure_liv,iddepot,num_echange,commentaires1,commentaires2,id_calendar,Actif,id_conducteur,id_chantier,etat) VALUES (:NumAffaire, :DateCde, :PoidTotMat, :DateEnlevDem, :HeureEnlevDem, :DateEnlevDem1, :HeureEnlevDem1, :DateEnlevDem2, :HeureEnlevDem2, :IdDepot, :NumEchange, :Commentaires1, :Commentaires2, :EventId, :Actif, :IdConduc, :IdChant, :Etat)';
					} else {
						$RecupId = $resultat->fetch();
						$NumId = trim($RecupId['id']);
						$eventId = $RecupId['id_calendar'];
						// Préparation de la requête de mise a jour
						$query = 'UPDATE cde_mat_ent SET num_affaire = :NumAffaire, date_cde = :DateCde, poids_tot_mat = :PoidTotMat, date_enlev_dem = :DateEnlevDem, heure_enlev_dem = :HeureEnlevDem, date_enlev_reel = :DateEnlevDem1, heure_enlev_reel = :HeureEnlevDem1, date_liv = :DateEnlevDem2, heure_liv = :HeureEnlevDem2, iddepot =:IdDepot, num_echange = :NumEchange, commentaires1 = :Commentaires1, commentaires2 = :Commentaires2, id_calendar = :EventId, Actif = :Actif, id_conducteur = :IdConduc, id_chantier = :IdChant, etat = :Etat WHERE num_echange = '.$NumCommande;
					}				
					//Obtention du fichier json
					$url = "https://cloud.layher.fr/get/".$NumCommande;
					$response = get_request($url, $AccessToken);
					if ($response) {
						$data = json_decode($response);
						//recuperation du Json dans $info
						$info = getAddressInfo($data);
						// au cas ou le codechantier serait saisi dans le nom client....
						$CodeChantierTemp2 = detecterChantier($info['NomClient']);
						//Recherche Id NumAffaire (Code Chantier Layher)
						$NumAffaire = ($PosPar8 !== false) ? $NumAffaire2 : $info['NumAffaire'];
						$IdNumAffaire = getNumAffaireId($pdo, $NumAffaire);
						if ($IdNumAffaire == 0) {
							$missing_NumAffaire = true;
						}
						//Date commande
						$DateCde = $dateHeureLisible;
						$DateCdeInv = substr($info['DateCdeDeb'], 0, 4) . substr($info['DateCdeDeb'], 5, 2) . substr($info['DateCdeDeb'], 8, 2);
						// rechercher Id Conducteur
						$IdConduc = getConducId($pdo, $info['Initiales'], $emailConduc);
						if ($IdConduc == 0) {
							$missing_CodeChantier = true;
						}
						//verifier si code chantier dans le champ initiales (au cas ou mauvaise saise du conducteur)
						$CodeChantierTemp = "";
						$taille = isset($info['Initiales']) ? strlen($info['Initiales']) : 0;						
						if ($taille >= 5 And ctype_digit($info['Initiales'])) { //si la taille >= 5 et qu'il n'y a que des chiffres
							//ce n'est pas des initiales mais peut etre le code chantier
							$CodeChantierTemp = $Initiales;
						}		
						//Recherche du code chantier
						$CodeChantier = $info['CodeChantier'];
						//si code chantier vide ou pas des chiffres, rechercher dans $CodeChantierTemp ou $CodeChantierTemp2 ou $CodeChantierTemp3
						if (!ctype_digit($CodeChantier)) {
							$CodeChantier = !empty($CodeChantierTemp) ? $CodeChantierTemp : ($CodeChantierTemp2 ?? $CodeChantierTemp3);
						}
						//si Code Chantier toujours vide
						if ($CodeChantier == '') {
							$missing_CodeChantier = true;
						}
						//Rechercher Date Enlevement
						$DateEnlevDem = ($info['DateEnlevDem'] == "" && $PosPar4 !== false) ? $DateEnlevDem2 : $info['DateEnlevDem'];
						if ($DateEnlevDem != "") {
							$DateEnlevDemInv = substr($DateEnlevDem, 0, 4) . substr($DateEnlevDem, 5, 2) . substr($DateEnlevDem, 8, 2);
							$DateVerifieInv = VerifDateEnlev($DateEnlevDemInv);
							if ($DateVerifieInv != $DateEnlevDemInv) {
								$DateEnlevDem = substr($DateVerifieInv, 0, 4) . "-" . substr($DateVerifieInv, 5, 2) . "-" . substr($DateVerifieInv, 8, 2);
							}
						} else {
							$missing_Date_Enlevement = true;
						}
						//Rechercher Heure enlevement
						$HeureEnlevDem = ($info['HeureEnlevDem'] == "" && $PosPar5 !== false) ? $HeureEnlevDem2 : $info['HeureEnlevDem'];
						if ($HeureEnlevDem == "") {
							$missing_Heure_Enlevement = true;
						}
						//rechercher IdAgence
						$IdAgence = ($info['Commentaires'][2] == "" && $PosPar6 !== false) ? $IdAgence2 : $info['Commentaires'][2];
						$IdAgence = $IdAgence == "" ? 1 : intval($IdAgence);
						//No du dépot concerné par la commande (1=Layher/2=Lagny)
						if ($CodeChantier == 20143) { //commande reassort Lagny donc commande materiel pour Layher obligatoire
							$IdDepot = 1;
						} else {
								$IdDepot = intval($info['Commentaires'][3]); // ou $data->m_clAdresse->m_stInformations->tabCommentaire[3]
								if ($IdDepot == "" && $PosPar7 !== false) {
								$IdDepot = $IdDepot2;
							}
							if ($IdDepot == "") {
								$IdDepot = 1; // Layher par defaut.
							}
						}
						//Recuperer valeur IdDepot et IdAgence
						$NumAgence = getAgenceId($pdo, $IdAgence);
						$NumDepot = getDepotId($pdo, $IdDepot);
						//verifier NumEchange 
						if ($info['NumEchange'] != $NumCommande) {
							$info['NumEchange'] = $NumCommande;
						}
						// on verifie que le code Chantier existe si $missing_CodeChantier = false
						//Recherche IdChantier à partir du code chantier que l'on a
						if ($missing_CodeChantier == false) {
							$resultCC = $pdo->query("Select * from chantiers where NumChantier = ".intval($CodeChantier));
							$estVideCC = ($resultCC->rowCount() == 0);
							if ($estVideCC) {
								writeLog("Code chantier : ".$CodeChantier." inconnu dans la base",$fichierLog,'ERROR');	
								$missing_CodeChantier = true;
							} else {
								$RecupCC = $resultCC->fetch();
								$IdChant = $RecupCC['Idchantier'];
								//Mise à jour de l'etat du chantier a "En cours = 2"
								$resultChant = $pdo->query("update chantiers set etat = 2 where NumChantier = ".intval($CodeChantier));								
							}
						}						
						// Verification des infos manquantes pour la recuperation	
						$informationsManquantes = $missing_CodeChantier || $missing_NumAffaire || $missing_Date_Enlevement || $missing_Heure_Enlevement;
						$expediteurNonAutorise = !in_array($expediteurMail, $expediteurs_autorises) && $expediteurMail !== null;
						$doDelete = false;
						if ($informationsManquantes || $expediteurNonAutorise) {
							if ($informationsManquantes) {
								enregistrerErreur($pdo, $expediteurMail, $objet, $missing_CodeChantier, $missing_NumAffaire, $missing_Date_Enlevement, $missing_Heure_Enlevement, $IdConduc);
								$doDelete = true;
								writeLog("Effacement message car info(s) manquante(s) pour le No echange : ".$NumCommande, $fichierLog, 'ERROR');
							}
							if ($expediteurNonAutorise and $expediteurMail !== null) {
								$doDelete = true;
								writeLog("Effacement message car expediteur non autorisé : ".$expediteurMail, $fichierLog, 'ERROR');
							}
							if ($doDelete) {
								// Supprimer le mail
								imap_delete($mailbox, 1);
								imap_expunge($mailbox);
							}
						} else {										
							//--------------------------------------------------------------------------------------------------------------------
							//insertion calendrier outlook	
							$DateDep = formatDate($DateEnlevDemInv);
							$HeureDep = $HeureEnlevDem . ":00";
							$HeureArr = addHoursToTime($HeureDep, 2);
							$DateStart = $DateDep . "T" . $HeureDep;
							$DateEnd = $DateDep . "T" . $HeureArr;							
							$DateStart2 = getDateTimeWithOffset($DateStart, 'P2D');
							$DateEnd2 = getDateTimeWithOffset($DateEnd, 'P5D');
							$TimeZone = "Europe/Paris";
							$category = "Demande CDT";
							$subject = "LIV - " . $info['NomClient'] . " - " . $info['VilleChantier'] . " - No : " . $info['NumEchange'];
							$content = "Code Chantier J4R : " . $CodeChantier . "<br>" . $info['Initiales'] . "<br>" . $info['Commentaires'][1] . "<br>" . $info['Commentaires'][2];
							$locationName = "Dépôt Layher";
							$event = createEventArray($DateStart, $DateEnd, $TimeZone, $category, $subject, $content, $locationName);
							
							if ($eventId == 0) {
								$Url = "https://graph.microsoft.com/v1.0/users/$UserId/calendars/$CalendarId/calendarview?startdatetime=$DateStart2&enddatetime=$DateEnd2&\$select=subject,start,end,location,categories,id&\$top=1000";
								$response = get_request($Url, $AccessToken);
								$jsonData = json_decode($response, true);
								$eventId = getEventId($jsonData, $subject);
								if ($eventId != 0) {
									writeLog("Modification calendrier outlook", $fichierLog);
								} else {
									$Url = "https://graph.microsoft.com/v1.0/users/$UserId/calendars/$CalendarId/events";
									$Patch = "Faux";
									$response = post_request($Url, $event, $AccessToken, $Patch);
									$Url = "https://graph.microsoft.com/v1.0/users/$UserId/calendars/$CalendarId/calendarview?startdatetime=$DateStart&enddatetime=$DateEnd";
									$response = get_request($Url, $AccessToken);
									$jsonData = json_decode($response, true);
									$eventId = getEventId($jsonData, $subject);
									if ($eventId != 0) {
										writeLog("Creation calendrier outlook", $fichierLog);
									} else {
										writeLog("Erreur lors de la création de l'événement dans le calendrier transports", $fichierLog,'ERROR');
									}									
								}
							} else {
								$Url = "https://graph.microsoft.com/v1.0/users/$UserId/calendars/$CalendarId/events/$eventId";
								$Patch = "PATCH";
								$response = post_request($Url, $event, $AccessToken, $Patch);
								writeLog("Modification calendrier outlook", $fichierLog);
							}							
							//--------------------------------------------------------------------------------------------------------------------
							//Insertion BDD ligne entete
							$stmt = $pdo->prepare($query);
							$stmt->bindParam(':NumAffaire', $NumAffaire);
							$stmt->bindParam(':DateCde', $DateCde);
							$stmt->bindParam(':PoidTotMat', $info['PoidsTotMat']);
							$stmt->bindParam(':DateEnlevDem', $DateEnlevDem);
							$stmt->bindParam(':HeureEnlevDem', $HeureEnlevDem);
							$stmt->bindParam(':DateEnlevDem1', $DateEnlevDem);
							$stmt->bindParam(':HeureEnlevDem1', $HeureEnlevDem);
							$stmt->bindParam(':DateEnlevDem2', $DateEnlevDem);
							$stmt->bindParam(':HeureEnlevDem2', $HeureEnlevDem);
							$stmt->bindParam(':IdDepot', $NumDepot); 
							$stmt->bindParam(':NumEchange', $info['NumEchange']); 
							$stmt->bindParam(':Commentaires1', $info['Commentaires'][1]);
							$stmt->bindParam(':Commentaires2', $info['Commentaires2'][2]);		
							$stmt->bindParam(':EventId', $eventId);	
							$stmt->bindParam(':Actif', $Actif);
							$stmt->bindParam(':IdConduc', $IdConduc);
							$stmt->bindParam(':IdChant', $IdChant);					
							$stmt->bindParam(':Etat', $Etat);
							$stmt->execute();
							// Récupération de l'ID de la nouvelle ligne
							$IdCdeMatEnt = getIdCdeMatEnt($pdo, $estVide, $NumId);
							
							$PoidsTotMat = 0;
							//insertion articles vente		
							if ($data && isset($data->m_tabArticleVente)) {
								$PoidsTotMat += insertOrUpdateArticles($pdo, $data->m_tabArticleVente, 'V', $IdCdeMatEnt, $estVide);
							} 			
							//insertion articles Location
							if ($data && isset($data->m_tabArticleLocation)) {
								$PoidsTotMat += insertOrUpdateArticles($pdo, $data->m_tabArticleLocation, 'L', $IdCdeMatEnt, $estVide);
							}			

							//Verification et insertion ou update dans table transports
							insertOrUpdateTransport($pdo, $IdCdeMatEnt, $PoidsTotMat, $DateCde, $DateEnlevDem, $eventId, $Etat, $IdChant, $IdNumAffaire, $HeureEnlevDem);
					
							//Effacement du message car mail traité
							writeLog("Effacement message sur serveur suite à integration réussie de la commande No : ".$NumCommande,$fichierLog);
							//imap_delete($mailbox, 1);
							//imap_expunge($mailbox);						
						}
					}
				} else {
					//si pas de numero de commande dans l'objet, on detruit le mail pour ne pas bloquer les commandes car ce n'est pas un message de commande
					//Effacement du message
					writeLog("Effacement message car le mail n'est pas une commande",$fichierLog);
					imap_delete($mailbox, 1);
					imap_expunge($mailbox);				
				}
			}
		}
		// Fermer la connexion à la boîte mail
		imap_close($mailbox);		
	}
}
?>
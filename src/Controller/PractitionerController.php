<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\RdvRepository;
use App\Repository\UserRepository;
use App\Entity\Rdv;
use App\Services\GeocodingService;
use App\Services\GeoService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/practitioner")
 */
class PractitionerController extends AbstractController
{
     /** @Route("/", name="practitioner_index")
     * @return Response
     */
    public function index()
    {
        $pract=new User;

        $pract = $this->getDoctrine()
            ->getRepository(User::class)
            ->findOneBy(["lastName" => "Doctor"]);

        $rdv = $this->getDoctrine()
            ->getRepository(RDV::class)
            ->findBy(["practitioner" => $pract]);

        $geoservice = new GeoService();
        $distance = $geoservice->calcDistance($pract->getCoordX(), $pract->getCoordY(), $rdv->getLatitude(), $rdv->getLongitude());

        return $this->render('practitioner/index.html.twig', [
            'rdvs' => $rdv,
            'distance' => $distance,
        ]);
    }

    /**
     * @Route("/list", name="practitioner_list")
     * @param GeocodingService $geocoding
     * @return Response
     */
    public function rdvlist(GeocodingService $geocoding)
    {

        // affectation  coordonées du docteur
        $doctor = $this->getDoctrine()
            ->getRepository(User::class)
            ->findOneBy(["lastName" => "Doctor"]);
        $gps=$geocoding->addresstoGPS($doctor->getAdress());
        $coordX = $gps["features"][0]['geometry']['coordinates'][1];
        $coordY = $gps["features"][0]['geometry']['coordinates'][0];
        $doctor->setCoordX($coordX);
        $doctor->setCoordY($coordY);

        $entityManager = $this->getDoctrine()->getManager();
        $entityManager->persist($doctor);


        // liste des rdv eligible pour le docteur
        $rdvList = [];

        // recupération des autres users et affectation des coordonées si pas deja existantes
        $patients = $doctor = $this->getDoctrine()
            ->getRepository(User::class)
            ->findAllNotInlude("Doctor");
        for($i =0 ; $i < count($patients) ; $i++)
        {
            /**
             * @var User[]
             */
            $aPatient = $patients[$i];
            // affectation coordonées user
            if(!$aPatient->getCoordX()) {
                $gps = $geocoding->addresstoGPS($aPatient->getAdress());
                $coordX = $gps["features"][0]['geometry']['coordinates'][1];
                $coordY = $gps["features"][0]['geometry']['coordinates'][0];
                $aPatient->setCoordX($coordX);
                $aPatient->setCoordY($coordY);
                $entityManager->persist($aPatient);

            }

            // ajout du  rdv a la liste , mettre les test ici
            $rdv = $aPatient->getRdv();
            $rdvList[] =$rdv;

        }
        $entityManager->flush();

        return $this->render('practitioner/list.html.twig', [
            'gps' => $gps,
        ]);
    }


    /**
     * @Route ("/map", name="practitioner_map")
     */
    public function map()
    {
        $rdvs = $this->getDoctrine()
            ->getRepository(Rdv::class)
            ->findBy(['isActive' => 1]);

        return $this->render('/practitioner/map.html.twig', [
            'rdvs' => $rdvs
        ]);
    }

    /**
     * @Route ("/map/{id}", name="practitioner_map_solo")
     * @param Rdv $rdv
     * @return Response
     */
    public function mapSolo(RDV $rdv)
    {
        $rdvs = $this->getDoctrine()
            ->getRepository(Rdv::class)
            ->findBy(['id' => $rdv]);

        return $this->render('/practitioner/map.html.twig', [
            'rdvs' => $rdvs
        ]);
    }

}

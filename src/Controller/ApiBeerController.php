<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Beer;
use App\Repository\BeerRepository;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Annotations as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @OA\Tag(name="Beer")
 */
class ApiBeerController extends AbstractController
{
    private BeerRepository $beerRepository;

    private EntityManagerInterface $entityManager;

    public function __construct(BeerRepository $beerRepository, EntityManagerInterface $entityManager)
    {
        $this->beerRepository = $beerRepository;
        $this->entityManager = $entityManager;
    }

    /**
     * @Route("/api/beer/beers", name="beer_list", methods={"GET"})
     * @OA\Response(
     *     response=200,
     *     description="Returns all beers",
     *     @OA\JsonContent(
     *        type="array",
     *        @OA\Items(ref="#/components/schemas/Beer")
     *     )
     * )
     */
    public function listBeers(): Response
    {
        $beers = $this->beerRepository->findAll();
        $res = [];
        foreach ($beers as $beer) {
            $res[] = $beer->__toArray();
        }

        return new JsonResponse($res);
    }

    /**
     * @Route("/api/beer/{beerId}/details", name="beer_details", methods={"GET"})
     * @OA\Parameter(name="beerId", in="path", description="UUID of beer")
     * @OA\Response(
     *     response=200,
     *     description="Returns specified beer details",
     *     @OA\JsonContent(
     *        ref="#/components/schemas/Beer"
     *     )
     * )
     * @OA\Response(
     *     response=404,
     *     description="Beer not found",
     *     @OA\JsonContent(
     *        type="object",
     *        @OA\Property(property="id", type="string"),
     *        @OA\Property(property="message", type="string"),
     *     )
     * )
     */
    public function beerDetails(string $beerId): Response
    {
        $beer = $this->beerRepository->find($beerId);
        if (! $beer) {
            return new JsonResponse([
                'message' => sprintf('Beer %s not found', $beerId),
            ], 404);
        }

        return new JsonResponse($beer->__toArray());
    }

    /**
     * @Route("/api/beer/add", name="beer_add", methods={"POST"})
     * @OA\RequestBody(
     *       required=true,
     *       description="Beer data",
     *       @OA\JsonContent(
     *          type="object",
     *          @OA\Property(property="brand", type="string"),
     *          @OA\Property(property="name", type="string"),
     *          @OA\Property(property="volume", type="number"),
     *          @OA\Property(property="alcohol", type="number"),
     *          @OA\Property(property="packing", type="string")
     *      )
     * )
     * @OA\Response(
     *     response=204,
     *     description="Successfully added new beer"
     * )
     * @OA\Response(
     *     response=400,
     *     description="Incorrect beer details",
     *     @OA\JsonContent(
     *        type="object",
     *        @OA\Property(property="message", type="string"),
     *        @OA\Property(property="details", type="string")
     *     )
     * )
     */
    public function addBeer(Request $request): Response
    {
        $requiredParams = ['brand', 'name', 'volume', 'alcohol', 'packing'];
        $requestParams = array_keys($request->toArray());
        $missingParams = array_values(array_diff($requiredParams, $requestParams));
        if (! empty($missingParams)) {
            return new JsonResponse([
                'message' => 'Incorrect request',
                'details' => sprintf('Missing following params: %s', implode(', ', $missingParams)),
            ], 400);
        }

        $packing = $request->toArray()['packing'];
        if (! in_array(strtolower($packing), ['can', 'bottle'], true)) {
            return new JsonResponse([
                'message' => 'Incorrect request',
                'details' => 'Incorrect packing type - allowed values: can, bottle',
            ], 400);
        }

        $beer = new Beer();
        $beer->setBrand($request->toArray()['brand']);
        $beer->setName($request->toArray()['name']);
        $beer->setVolume($request->toArray()['volume']);
        $beer->setAlcohol($request->toArray()['alcohol']);
        $beer->setPacking(strtolower($packing) === 'can' ? Beer::CAN : Beer::BOTTLE);

        $this->entityManager->persist($beer);
        $this->entityManager->flush();

        return new Response(null, 204);
    }
}

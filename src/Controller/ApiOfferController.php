<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Offer;
use App\Repository\BeerRepository;
use App\Repository\HistoryRepository;
use App\Repository\OfferRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Annotations as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @OA\Tag(name="Offer")
 */
class ApiOfferController extends AbstractController
{
    private BeerRepository $beerRepository;

    private EntityManagerInterface $entityManager;

    private HistoryRepository $historyRepository;

    private OfferRepository $offerRepository;

    private UserRepository $userRepository;

    public function __construct(
        BeerRepository $beerRepository,
        EntityManagerInterface $entityManager,
        HistoryRepository $historyRepository,
        OfferRepository $offerRepository,
        UserRepository $userRepository
    ) {
        $this->beerRepository = $beerRepository;
        $this->entityManager = $entityManager;
        $this->historyRepository = $historyRepository;
        $this->offerRepository = $offerRepository;
        $this->userRepository = $userRepository;
    }

    /**
     * @Route("/api/offer/offers", name="offer_list", methods={"GET"})
     * @OA\Response(
     *     response=200,
     *     description="Returns all offers",
     *     @OA\JsonContent(
     *        type="array",
     *        @OA\Items(ref="#/components/schemas/Offer")
     *     )
     * )
     */
    public function listOffers(): Response
    {
        $offers = $this->offerRepository->findAll();
        $res = [];
        foreach ($offers as $offer) {
            $res[] = $offer->__toArray();
        }

        return new JsonResponse($res);
    }

    /**
     * @Route("/api/offer/buy/offers", name="offer_list_buy", methods={"GET"})
     * @OA\Response(
     *     response=200,
     *     description="Returns all buying offers",
     *     @OA\JsonContent(
     *        type="array",
     *        @OA\Items(ref="#/components/schemas/Offer")
     *     )
     * )
     */
    public function offersBuy(): Response
    {
        $offers = $this->offerRepository->findAllBuy();
        $res = [];
        foreach ($offers as $offer) {
            $res[] = $offer->__toArray();
        }

        return new JsonResponse($res);
    }

    /**
     * @Route("/api/offer/sell/offers", name="offer_list_sell", methods={"GET"})
     * @OA\Response(
     *     response=200,
     *     description="Returns all selling offers",
     *     @OA\JsonContent(
     *        type="array",
     *        @OA\Items(ref="#/components/schemas/Offer")
     *     )
     * )
     */
    public function offersSell(): Response
    {
        $offers = $this->offerRepository->findAllSell();
        $res = [];
        foreach ($offers as $offer) {
            $res[] = $offer->__toArray();
        }

        return new JsonResponse($res);
    }

    /**
     * @Route("/api/offer/{offerId}/details", name="offer_details", methods={"GET"})
     * @OA\Parameter(name="offerId", in="path", description="UUID of offer")
     * @OA\Response(
     *     response=200,
     *     description="Returns specified offer details",
     *     @OA\JsonContent(
     *        ref="#/components/schemas/Offer"
     *     )
     * )
     * @OA\Response(
     *     response=404,
     *     description="Offer does not exist",
     *     @OA\JsonContent(
     *        type="object",
     *        @OA\Property(property="message", type="string")
     *     )
     * )
     */
    public function offerDetails(string $offerId): Response
    {
        $offer = $this->offerRepository->find($offerId);
        if (! $offer) {
            return new JsonResponse([
                'message' => sprintf('Offer %s not found', $offerId),
            ], 404);
        }

        return new JsonResponse($offer->__toArray());
    }

    /**
     * @Route("/api/offer/find/{x}/{y}/{radius}", name="offer_nearby", methods={"GET"})
     * @OA\Parameter(name="x", in="path", description="User's location latitude")
     * @OA\Parameter(name="y", in="path", description="User's location longitude")
     * @OA\Parameter(name="radius", in="path", description="Radius to find offers nearby")
     * @OA\Response(
     *     response=200,
     *     description="Returns list of offers nearby specified location",
     *     @OA\JsonContent(
     *        type="array",
     *        @OA\Items(ref="#/components/schemas/Offer")
     *     )
     * )
     */
    public function nearbyOffers(float $x, float $y, float $radius): Response
    {
        $offers = $this->offerRepository->findAll();
        $res = [];
        foreach ($offers as $offer) {
            if ($offer->distanceTo($x, $y) <= $radius) {
                $res[] = $offer->__toArray();
            }
        }

        return new JsonResponse($res);
    }

    /**
     * @Route("/api/offer/add", name="offer_add", methods={"POST"})
     * @OA\RequestBody(
     *       required=true,
     *       description="Offer data",
     *       @OA\JsonContent(
     *          type="object",
     *          @OA\Property(property="owner", type="string"),
     *          @OA\Property(property="beer", type="string"),
     *          @OA\Property(property="amount", type="number"),
     *          @OA\Property(property="price", type="number"),
     *          @OA\Property(property="location", ref="#/components/schemas/Location"),
     *          @OA\Property(property="type", type="string")
     *      )
     * )
     * @OA\Response(
     *     response=204,
     *     description="Successfully added new offer"
     * )
     * @OA\Response(
     *     response=400,
     *     description="Incorrect offer details",
     *     @OA\JsonContent(
     *        type="object",
     *        @OA\Property(property="message", type="string"),
     *        @OA\Property(property="details", type="string")
     *     )
     * )
     */
    public function addOffer(Request $request): Response
    {
        $requiredParams = ['owner', 'beer', 'amount', 'price', 'location', 'type'];
        $requestParams = array_keys($request->toArray());
        $missingParams = array_values(array_diff($requiredParams, $requestParams));
        if (! empty($missingParams)) {
            return new JsonResponse([
                'message' => 'Incorrect request',
                'details' => sprintf('Missing following params: %s', implode(', ', $missingParams)),
            ], 400);
        }

        $user = $this->userRepository->find($request->toArray()['owner']);
        if (! $user) {
            return new JsonResponse([
                'message' => 'Incorrect request',
                'details' => sprintf('User %s not found', $request->toArray()['owner']),
            ], 400);
        }

        $beer = $this->beerRepository->find($request->toArray()['beer']);
        if (! $beer) {
            return new JsonResponse([
                'message' => 'Incorrect request',
                'details' => sprintf('Beer %s not found', $request->toArray()['beer']),
            ], 400);
        }

        $location = $request->toArray()['location'];
        $missingParams = array_values(array_diff(['x', 'y'], array_keys($location)));
        if (! empty($missingParams)) {
            return new JsonResponse([
                'message' => 'Incorrect request',
                'details' => sprintf('Missing following params in location: %s', implode(', ', $missingParams)),
            ], 400);
        }

        $type = $request->toArray()['type'];
        if (! in_array(strtolower($type), ['buy', 'sell'], true)) {
            return new JsonResponse([
                'message' => 'Incorrect request',
                'details' => 'Incorrect packing type - allowed values: buy, sell',
            ], 400);
        }

        $offer = new Offer();
        $offer->setOwner($user);
        $offer->setBeer($beer);
        $offer->setAmount($request->toArray()['amount']);
        $offer->setPrice($request->toArray()['price']);
        $offer->setLocation($location['x'], $location['y']);
        $offer->setTypeOfTransaction(strtolower($type) === 'buy' ? Offer::BUY : Offer::SELL);

        $this->entityManager->persist($offer);
        $this->entityManager->flush();

        return new Response(null, 204);
    }

    /**
     * @Route("/api/offer/{offerId}/buy", name="offer_buy", methods={"POST"})
     * @OA\Parameter(name="offerId", in="path", description="UUID of offer")
     * @OA\RequestBody(
     *       required=true,
     *       description="Offer data",
     *       @OA\JsonContent(
     *          type="object",
     *          @OA\Property(property="buyer", type="string"),
     *          @OA\Property(property="amount", type="number")
     *      )
     * )
     * @OA\Response(
     *     response=204,
     *     description="Successfully made transaction"
     * )
     * @OA\Response(
     *     response=400,
     *     description="Incorrect offer details",
     *     @OA\JsonContent(
     *        type="object",
     *        @OA\Property(property="message", type="string")
     *     )
     * )
     * @OA\Response(
     *     response=404,
     *     description="Offer does not exists",
     *     @OA\JsonContent(
     *        type="object",
     *        @OA\Property(property="message", type="string")
     *     )
     * )
     *
     * @codeCoverageIgnore
     */
    public function buyOffer(string $offerId): Response
    {
        $offer = $this->offerRepository->find($offerId);
        if (! $offer) {
            return new JsonResponse([
                'message' => sprintf('Offer %s not found', $offerId),
            ], 404);
        }

        // TODO: implement

        return new Response(null, 204);
    }

    /**
     * @Route("/api/offer/{offerId}/update", name="offer_update", methods={"PUT"})
     * @OA\Parameter(name="offerId", in="path", description="UUID of offer")
     * @OA\RequestBody(
     *     required=true,
     *     description="Offer data that is being updated",
     *     @OA\JsonContent(
     *        type="object",
     *        @OA\Property(property="beer", type="string"),
     *        @OA\Property(property="amount", type="number"),
     *        @OA\Property(property="price", type="number"),
     *        @OA\Property(property="location", ref="#/components/schemas/Location"),
     *     ),
     * )
     * @OA\Response(
     *     response=204,
     *     description="Successfully changed offer's details"
     * )
     * @OA\Response(
     *     response=400,
     *     description="Incorrect request",
     *     @OA\JsonContent(
     *        type="object",
     *        @OA\Property(property="message", type="string")
     *     )
     * )
     * @OA\Response(
     *     response=404,
     *     description="Offer does not exists",
     *     @OA\JsonContent(
     *        type="object",
     *        @OA\Property(property="message", type="string")
     *     )
     * )
     */
    public function updateOffer(string $offerId, Request $request): Response
    {
        $offer = $this->offerRepository->find($offerId);
        if (! $offer) {
            return new JsonResponse([
                'message' => sprintf('Offer %s not found', $offerId),
            ], 404);
        }

        $requiredParams = ['beer', 'amount', 'price', 'location'];
        $requestParams = array_keys($request->toArray());
        $missingParams = array_values(array_diff($requiredParams, $requestParams));
        if (! empty($missingParams)) {
            return new JsonResponse([
                'message' => 'Incorrect request',
                'details' => sprintf('Missing following params: %s', implode(', ', $missingParams)),
            ], 400);
        }

        $beer = $this->beerRepository->find($request->toArray()['beer']);
        if (! $beer) {
            return new JsonResponse([
                'message' => 'Incorrect request',
                'details' => sprintf('Beer %s not found', $request->toArray()['beer']),
            ], 400);
        }

        $location = $request->toArray()['location'];
        $missingParams = array_values(array_diff(['x', 'y'], array_keys($location)));
        if (! empty($missingParams)) {
            return new JsonResponse([
                'message' => 'Incorrect request',
                'details' => sprintf('Missing following params in location: %s', implode(', ', $missingParams)),
            ], 400);
        }

        $offer->setBeer($beer);
        $offer->setAmount($request->toArray()['amount']);
        $offer->setPrice($request->toArray()['price']);
        $offer->setLocation($location['x'], $location['y']);

        $this->entityManager->flush();

        return new Response(null, 204);
    }

    /**
     * @Route("/api/offer/{offerId}/delete", name="offer_delete", methods={"DELETE"})
     * @OA\Parameter(name="offerId", in="path", description="UUID of offer")
     * @OA\Response(
     *     response=204,
     *     description="Successfully removed offer"
     * )
     * @OA\Response(
     *     response=404,
     *     description="Offer does not exists",
     *     @OA\JsonContent(
     *        type="object",
     *        @OA\Property(property="message", type="string")
     *     )
     * )
     */
    public function deleteOffer(string $offerId): Response
    {
        $offer = $this->offerRepository->find($offerId);
        if (! $offer) {
            return new JsonResponse([
                'message' => sprintf('Offer %s not found', $offerId),
            ], 404);
        }

        $history = $this->historyRepository->findAllByOffer($offer);

        foreach ($history as $transaction) {
            $transaction->setOffer(null);
        }

        $this->entityManager->remove($offer);
        $this->entityManager->flush();

        return new Response(null, 204);
    }
}

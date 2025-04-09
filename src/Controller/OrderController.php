<?php

namespace App\Controller;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Repository\OrderRepository;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class OrderController extends AbstractController
{
    #[Route('/orders', name: 'app_order_index', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function index(OrderRepository $orderRepository): Response
    {
        $orders = $orderRepository->findAll();
        return $this->render('order/index.html.twig', [
            'orders' => $orders
        ]);
    }

    #[Route('/order/{id}', name: 'app_order_show', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function show(Order $order): Response
    {
        return $this->render('order/show.html.twig', [
            'order' => $order
        ]);
    }

    #[Route('/api/orders', name: 'api_orders_list', methods: ['GET'])]
    public function apiIndex(OrderRepository $orderRepository): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'User not authenticated'], 401);
        }

        if (in_array('ROLE_ADMIN', $user->getRoles())) {
            $orders = $orderRepository->findAll();
        } else {
            $orders = $orderRepository->findBy(['user' => $user]);
        }

        return $this->json($orders, 200, [], ['groups' => 'order']);
    }

    #[Route('/api/order/{id}', name: 'api_order_show', methods: ['GET'])]
    public function apiShow(Order $order): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'User not authenticated'], 401);
        }

        if (!in_array('ROLE_ADMIN', $user->getRoles()) && $order->getUser() !== $user) {
            return $this->json(['error' => 'Access denied'], 403);
        }

        return $this->json($order, 200, [], ['groups' => 'order']);
    }

    #[Route('/api/order/create', name: 'api_order_create', methods: ['POST'])]
    public function apiCreate(Request $request, EntityManagerInterface $entityManager, ProductRepository $productRepository): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'User not authenticated'], 401);
        }

        $data = json_decode($request->getContent(), true);
        if (!isset($data['items']) || !is_array($data['items'])) {
            return $this->json(['error' => 'Invalid order data'], 400);
        }

        $invalidProducts = [];
        $order = new Order();
        $order->setUser($user);

        foreach ($data['items'] as $itemData) {
            if (!isset($itemData['productId']) || !isset($itemData['quantity'])) {
                return $this->json(['error' => 'Invalid item data'], 400);
            }

            $product = $productRepository->find($itemData['productId']);
            if (!$product) {
                $invalidProducts[] = $itemData['productId'];
                continue;
            }

            $orderItem = new OrderItem();
            $orderItem->setProduct($product);
            $orderItem->setQuantity($itemData['quantity']);
            $orderItem->setPrice($product->getPrice());
            $order->addItem($orderItem);
        }

        if (!empty($invalidProducts)) {
            return $this->json(['error' => 'Invalid products', 'invalidProducts' => $invalidProducts], 400);
        }

        $order->calculateTotalPrice();
        $entityManager->persist($order);
        $entityManager->flush();

        return $this->json($order, 201, [], ['groups' => 'order']);
    }
}

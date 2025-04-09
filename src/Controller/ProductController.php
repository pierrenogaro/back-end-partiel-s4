<?php

namespace App\Controller;

use App\Entity\Product;
use App\Form\ProductType;
use App\Repository\ProductRepository;
use App\Service\QrCode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class ProductController extends AbstractController
{
    #[Route('/back/products', name: 'api_products_list', methods: ['GET'])]
    public function apiIndex(ProductRepository $productRepository): JsonResponse
    {
        $products = $productRepository->findAll();
        return $this->json($products);
    }

    #[Route('/api/back/product/create', name: 'api_product_create', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function apiCreate(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $product = new Product();
        $product->setName($data['name']);
        $product->setDescription($data['description']);
        $product->setPrice($data['price']);

        if (isset($data['image'])) {
            $product->setImage($data['image']);
        }

        $entityManager->persist($product);
        $entityManager->flush();

        return $this->json($product, 201);
    }

    #[Route('/back/product/{id}', name: 'api_product_show', methods: ['GET'])]
    public function apiShow(Product $product): JsonResponse
    {
        return $this->json($product);
    }

    #[Route('/api/back/product/update/{id}', name: 'api_product_update', methods: ['PUT'])]
    #[IsGranted('ROLE_ADMIN')]
    public function apiUpdate(Request $request, Product $product, EntityManagerInterface $entityManager): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $product->setName($data['name']);
        $product->setDescription($data['description']);
        $product->setPrice($data['price']);

        if (isset($data['image'])) {
            $product->setImage($data['image']);
        }

        $entityManager->flush();

        return $this->json($product);
    }

    #[Route('/api/back/product/delete/{id}', name: 'api_product_delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN')]
    public function apiDelete(Product $product, EntityManagerInterface $entityManager): JsonResponse
    {
        $entityManager->remove($product);
        $entityManager->flush();

        return $this->json(['message' => 'Product deleted successfully'], 200);
    }

    #[Route('/', name: 'app_product_index', methods: ['GET'])]
    public function index(ProductRepository $productRepository): Response
    {
        $products = $productRepository->findAll();
        return $this->render('product/index.html.twig', [
            'products' => $products
        ]);
    }

    #[Route('/product/new', name: 'app_product_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $product = new Product();
        $form = $this->createForm(ProductType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($product);
            $entityManager->flush();

            $this->addFlash('success', 'Product created successfully');
            return $this->redirectToRoute('app_product_index');
        }

        return $this->render('product/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/product/{id}', name: 'app_product_show', methods: ['GET'])]
    public function show(Product $product, QrCode $qrCodeService): Response
    {

        $qrcode = $qrCodeService->generateQrCodeForProduct($product);

        return $this->render('product/show.html.twig', [
            'product' => $product,
            'qrcode' => $qrcode,
        ]);
    }

    #[Route('/product/{id}/edit', name: 'app_product_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function edit(Request $request, Product $product, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(ProductType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Product updated successfully');
            return $this->redirectToRoute('app_product_show', ['id' => $product->getId()]);
        }

        return $this->render('product/edit.html.twig', [
            'product' => $product,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/product/{id}/delete', name: 'app_product_delete', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(Request $request, Product $product, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$product->getId(), $request->request->get('_token'))) {
            $entityManager->remove($product);
            $entityManager->flush();
            $this->addFlash('success', 'Product deleted successfully');
        }

        return $this->redirectToRoute('app_product_index');
    }

    #[Route('/product/{id}/qrcode', name: 'app_product_qrcode', methods: ['GET'])]
    public function generateQrCode(
        Product $product,
        QrCode $qrCode
    ): Response
    {
        $qrCodeDataUri = $qrCode->generateQrCodeForProduct($product);

        return $this->render('product/qrcode.html.twig', [
            'product' => $product,
            'qrcode' => $qrCodeDataUri
        ]);
    }
}
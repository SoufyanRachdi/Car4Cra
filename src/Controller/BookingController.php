<?php

namespace App\Controller;

use App\Entity\Booking;
use App\Entity\Car;
use App\Form\BookingType;
use App\Repository\BookingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/booking')]
#[IsGranted('ROLE_USER')]
class BookingController extends AbstractController
{
    #[Route('/my-bookings', name: 'app_booking_my', methods: ['GET'])]
    public function myBookings(BookingRepository $bookingRepository): Response
    {
        return $this->render('booking/my_bookings.html.twig', [
            'bookings' => $bookingRepository->findBy(['user' => $this->getUser()], ['startDate' => 'DESC']),
        ]);
    }

    #[Route('/new/{id}', name: 'app_booking_new', methods: ['GET', 'POST'])]
    public function new(Request $request, Car $car, EntityManagerInterface $entityManager): Response
    {
        if (!$car->isIsAvailable()) {
            $this->addFlash('danger', 'This car is not available for booking.');
            return $this->redirectToRoute('app_car_show', ['id' => $car->getId()]);
        }

        $booking = new Booking();
        $booking->setCar($car);
        $booking->setUser($this->getUser());

        $form = $this->createForm(BookingType::class, $booking);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Calculate total price
            $days = $booking->getStartDate()->diff($booking->getEndDate())->days;
            if ($days < 1)
                $days = 1; // Minimum 1 day

            $totalPrice = $days * $car->getPricePerDay();
            $booking->setTotalPrice((string) $totalPrice);
            $booking->setStatus('confirmed'); // Auto-confirm for now

            // Update car availability
            $car->setIsAvailable(false);

            $entityManager->persist($booking);
            $entityManager->flush();

            $this->addFlash('success', 'Booking confirmed successfully!');

            return $this->redirectToRoute('app_booking_my', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('booking/new.html.twig', [
            'booking' => $booking,
            'car' => $car,
            'form' => $form,
        ]);
    }

    #[Route('/admin/bookings', name: 'admin_booking_index', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function adminBookings(BookingRepository $bookingRepository): Response
    {
        return $this->render('booking/admin_index.html.twig', [
            'bookings' => $bookingRepository->findBy([], ['startDate' => 'DESC']),
        ]);
    }

    #[Route('/admin/booking/{id}/delete', name: 'admin_booking_delete', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function adminDelete(Request $request, Booking $booking, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $booking->getId(), $request->request->get('_token'))) {
            $entityManager->remove($booking);
            $entityManager->flush();
            $this->addFlash('success', 'Booking deleted successfully.');
        }

        return $this->redirectToRoute('admin_booking_index', [], Response::HTTP_SEE_OTHER);
    }
}

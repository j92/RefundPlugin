<?php

declare(strict_types=1);

namespace spec\Sylius\RefundPlugin\Converter;

use Doctrine\Common\Collections\ArrayCollection;
use PhpSpec\ObjectBehavior;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Sylius\RefundPlugin\Converter\LineItemsConverterInterface;
use Sylius\RefundPlugin\Entity\AdjustmentInterface;
use Sylius\RefundPlugin\Entity\LineItem;
use Sylius\RefundPlugin\Entity\ShipmentInterface;
use Sylius\RefundPlugin\Model\ShipmentRefund;
use Sylius\RefundPlugin\Provider\TaxRateAmountProviderInterface;

final class ShipmentLineItemsConverterSpec extends ObjectBehavior
{
    function let(RepositoryInterface $adjustmentRepository, TaxRateAmountProviderInterface $taxRateAmountProvider): void
    {
        $this->beConstructedWith($adjustmentRepository, $taxRateAmountProvider);
    }

    function it_implements_line_items_converter_interface(): void
    {
        $this->shouldImplement(LineItemsConverterInterface::class);
    }

    function it_converts_shipment_unit_refunds_to_line_items(
        RepositoryInterface $adjustmentRepository,
        AdjustmentInterface $shippingAdjustment,
        ShipmentInterface $shipment,
        TaxRateAmountProviderInterface $taxRateAmountProvider
    ): void {
        $shipmentRefund = new ShipmentRefund(1, 500);

        $adjustmentRepository
            ->findOneBy(['id' => 1, 'type' => AdjustmentInterface::SHIPPING_ADJUSTMENT])
            ->willReturn($shippingAdjustment)
        ;

        $shippingAdjustment->getShipment()->willReturn($shipment);

        $shipment->getAdjustmentsTotal()->willReturn(1000);

        $shipment->getAdjustments(AdjustmentInterface::TAX_ADJUSTMENT)->willReturn( new ArrayCollection([$shippingAdjustment->getWrappedObject()]));

        $taxRateAmountProvider->provide($shippingAdjustment)->willReturn(0.15);

        $shippingAdjustment->getLabel()->willReturn('Galaxy post');

        $this->convert([$shipmentRefund])->shouldBeLike([new LineItem(
            'Galaxy post',
            1,
            425,
            500,
            425,
            500,
            75,
            '15%'
        )]);
    }

    function it_throws_an_exception_if_there_is_no_shipping_adjustment_with_given_id(
        RepositoryInterface $adjustmentRepository
    ): void {
        $shipmentRefund = new ShipmentRefund(1, 500);

        $adjustmentRepository
            ->findOneBy(['id' => 1, 'type' => AdjustmentInterface::SHIPPING_ADJUSTMENT])
            ->willReturn(null)
        ;

        $this
            ->shouldThrow(\InvalidArgumentException::class)
            ->during('convert', [[$shipmentRefund]])
        ;
    }

    function it_throws_an_exception_if_refund_amount_is_higher_than_shipping_amount(
        RepositoryInterface $adjustmentRepository,
        AdjustmentInterface $shippingAdjustment,
        ShipmentInterface $shipment
    ): void {
        $shipmentRefund = new ShipmentRefund(1, 1001);

        $adjustmentRepository
            ->findOneBy(['id' => 1, 'type' => AdjustmentInterface::SHIPPING_ADJUSTMENT])
            ->willReturn($shippingAdjustment)
        ;

        $shippingAdjustment->getShipment()->willReturn($shipment);
        $shipment->getAdjustmentsTotal()->willReturn(1000);

        $this
            ->shouldThrow(\InvalidArgumentException::class)
            ->during('convert', [[$shipmentRefund]])
        ;
    }
}

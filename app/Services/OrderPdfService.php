<?php

namespace App\Services;

use App\Models\Order;
use Spatie\Browsershot\Browsershot;
use Illuminate\Support\Facades\View;

class OrderPdfService
{
    /**
     * Generate PDF for order summary
     *
     * @param Order $order
     * @return string PDF content as binary string
     */
    public function generateOrderSummaryPdf(Order $order): string
    {
        // Load all necessary relationships
        $order->load([
            'country',
            'pricingPlan',
            'company',
            'company.owners',
            'company.addresses',
            'company.country',
            'company.state',
            'state',
            'user'
        ]);

        // Prepare data for the view
        $data = $this->prepareOrderData($order);

        // Render the HTML template
        $html = View::make('pdf.order-summary', $data)->render();

        // Try to find Chrome executable
        $chromePath = $this->findChromePath();

        // Generate PDF using Browsershot
        $browsershot = Browsershot::html($html)
            ->setOption('viewport', [
                'width' => 1200,
                'height' => 1600,
            ])
            ->margins(20, 20, 20, 20)
            ->format('A4');

        // Set Chrome executable path if found
        if ($chromePath) {
            $browsershot->setOption('executablePath', $chromePath);
        }

        $pdf = $browsershot->pdf();

        return $pdf;
    }

    /**
     * Generate PDF for order summary from form data (before order is created)
     *
     * @param array $formData
     * @param string $userEmail
     * @return string PDF content as binary string
     */
    public function generateOrderSummaryPdfFromFormData(array $formData, string $userEmail): string
    {
        // Prepare data for the view from form data
        $data = $this->prepareFormDataForPdf($formData, $userEmail);

        // Render the HTML template
        $html = View::make('pdf.order-summary', $data)->render();

        // Try to find Chrome executable
        $chromePath = $this->findChromePath();

        // Generate PDF using Browsershot
        $browsershot = Browsershot::html($html)
            ->setOption('viewport', [
                'width' => 1200,
                'height' => 1600,
            ])
            ->setOption('args', ['--no-sandbox', '--disable-setuid-sandbox'])
            ->margins(20, 20, 20, 20)
            ->format('A4');

        // Set Chrome executable path if found
        if ($chromePath) {
            $browsershot->setOption('executablePath', $chromePath);
        }

        $pdf = $browsershot->pdf();

        return $pdf;
    }

    /**
     * Prepare order data for the PDF template
     *
     * @param Order $order
     * @return array
     */
    protected function prepareOrderData(Order $order): array
    {
        $pricingPlan = $order->pricingPlan;
        $company = $order->company;
        $user = $order->user;

        // Format pricing plan name (remove _uk suffix if present)
        $pricingPlanName = $pricingPlan ? $pricingPlan->name : 'N/A';
        $pricingPlanName = str_replace('_uk', '', $pricingPlanName);
        $pricingPlanName = ucfirst($pricingPlanName);

        // Format category labels
        $categoryLabels = [];
        if ($company && $company->category) {
            $categories = [
                'tech' => 'Tech',
                'retail' => 'Retail',
                'marketing' => 'Marketing',
                'consulting' => 'Consulting',
                'education' => 'Education',
                'entertainment' => 'Entertainment',
                'manufacturing' => 'Manufacturing',
                'finance' => 'Finance',
                'real-estate' => 'Real Estate',
                'logistics' => 'Logistics',
                'food' => 'Food',
                'wellness' => 'Wellness',
                'hospitality' => 'Hospitality',
                'construction' => 'Construction',
                'legal' => 'Legal',
                'other' => 'Other',
            ];

            foreach ((array) $company->category as $catId) {
                if (isset($categories[$catId])) {
                    $categoryLabels[] = $categories[$catId];
                } else {
                    $categoryLabels[] = ucfirst($catId);
                }
            }
        }

        // Format company address
        $companyAddressString = 'N/A';
        if ($company && $company->addresses && $company->addresses->isNotEmpty()) {
            $address = $company->addresses->first();
            $addressParts = array_filter([
                $address->street_address,
                $address->city,
                $address->state,
                $address->zip_code,
                $address->country,
            ]);
            $companyAddressString = !empty($addressParts) ? implode(', ', $addressParts) : 'N/A';
        }

        // Format address (for the Address section at the bottom - keeping for backward compatibility)
        $addressString = $companyAddressString;

        // Format payment status
        $paymentStatus = $order->payment_status ? $order->payment_status->value : 'unpaid';
        $paymentStatusLabel = ucfirst($paymentStatus);

        // Format owners with addresses
        $ownersWithAddresses = collect();
        if ($company && $company->owners) {
            foreach ($company->owners as $owner) {
                $ownerAddressString = 'N/A';
                if ($owner->address && is_array($owner->address)) {
                    $addressParts = array_filter([
                        $owner->address['streetAddress'] ?? $owner->address['street_address'] ?? '',
                        $owner->address['city'] ?? '',
                        $owner->address['state'] ?? '',
                        $owner->address['zipCode'] ?? $owner->address['zip_code'] ?? '',
                        $owner->address['country'] ?? '',
                    ]);
                    $ownerAddressString = !empty($addressParts) ? implode(', ', $addressParts) : 'N/A';
                }
                
                $ownersWithAddresses->push((object) [
                    'full_name' => $owner->full_name,
                    'ownership_percentage' => $owner->ownership_percentage,
                    'address' => $ownerAddressString,
                ]);
            }
        }

        return [
            'order' => $order,
            'pricingPlanName' => $pricingPlanName,
            'countryName' => $order->country ? $order->country->name : 'N/A',
            'userEmail' => $user ? $user->email : 'N/A',
            'paymentStatus' => $paymentStatusLabel,
            'orderStatus' => $order->status ? $order->status->label() : 'N/A',
            'companyStatus' => $company->status ? $company->status->label() : 'N/A',
            'company' => $company,
            'companyName' => $company ? $company->name : 'N/A',
            'companyAddressString' => $companyAddressString,
            'categoryLabels' => $categoryLabels,
            'stateName' => $order->state ? $order->state->name : null,
            'stateFee' => $order->state ? number_format($order->state->formation_fee ?? 0, 2) : '0.00',
            'owners' => $ownersWithAddresses,
            'addressString' => $addressString,
            'isUKPlan' => $pricingPlanName && (stripos($pricingPlanName, 'uk') !== false || stripos($pricingPlanName, 'united kingdom') !== false),
        ];
    }

    /**
     * Find Chrome executable path
     *
     * @return string|null
     */
    protected function findChromePath(): ?string
    {
        // Check for chrome-headless-shell in Puppeteer cache (most recent version)
        $homeDir = getenv('HOME') ?: getenv('USERPROFILE') ?: '/home/' . get_current_user();
        $puppeteerCache = $homeDir . '/.cache/puppeteer/chrome-headless-shell';
        
        if (is_dir($puppeteerCache)) {
            // Find the most recent version
            $versions = glob($puppeteerCache . '/linux-*');
            if (!empty($versions)) {
                // Sort by version number (newest first)
                usort($versions, function($a, $b) {
                    return version_compare(basename($b), basename($a));
                });
                
                $chromePath = $versions[0] . '/chrome-headless-shell-linux64/chrome-headless-shell';
                if (file_exists($chromePath) && is_executable($chromePath)) {
                    return $chromePath;
                }
            }
        }

        // Check common system Chrome locations
        $chromePaths = [
            '/usr/bin/google-chrome',
            '/usr/bin/chromium',
            '/usr/bin/chromium-browser',
            '/usr/local/bin/google-chrome',
            '/usr/local/bin/chromium',
            '/opt/google/chrome/chrome',
        ];

        foreach ($chromePaths as $path) {
            if (file_exists($path) && is_executable($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * Prepare form data for the PDF template
     *
     * @param array $formData
     * @param string $userEmail
     * @return array
     */
    protected function prepareFormDataForPdf(array $formData, string $userEmail): array
    {
        // Extract plan information
        $plan = $formData['plan'][0] ?? null;
        $pricingPlanName = $plan['pricingPlan'] ?? 'N/A';
        $pricingPlanName = str_replace('_uk', '', $pricingPlanName);
        $pricingPlanName = ucfirst($pricingPlanName);
        $countryName = $plan['countryName'] ?? 'N/A';

        // Format category labels
        $categoryLabels = [];
        $categories = [
            'tech' => 'Tech',
            'retail' => 'Retail',
            'marketing' => 'Marketing',
            'consulting' => 'Consulting',
            'education' => 'Education',
            'entertainment' => 'Entertainment',
            'manufacturing' => 'Manufacturing',
            'finance' => 'Finance',
            'real-estate' => 'Real Estate',
            'logistics' => 'Logistics',
            'food' => 'Food',
            'wellness' => 'Wellness',
            'hospitality' => 'Hospitality',
            'construction' => 'Construction',
            'legal' => 'Legal',
            'other' => 'Other',
        ];

        $companyCategory = $formData['category'] ?? [];
        foreach ((array) $companyCategory as $catId) {
            if (isset($categories[$catId])) {
                $categoryLabels[] = $categories[$catId];
            } else {
                $categoryLabels[] = ucfirst($catId);
            }
        }

        // Format company address
        $companyAddressString = 'N/A';
        $address = $formData['address'] ?? [];
        $addressParts = array_filter([
            $address['streetAddress'] ?? '',
            $address['city'] ?? '',
            $address['state'] ?? '',
            $address['zipCode'] ?? '',
            $address['country'] ?? '',
        ]);
        $companyAddressString = !empty($addressParts) ? implode(', ', $addressParts) : 'N/A';
        
        // Format address (for the Address section at the bottom - keeping for backward compatibility)
        $addressString = $companyAddressString;

        // Format owners with addresses
        $owners = collect();
        if (isset($formData['owners']) && is_array($formData['owners'])) {
            foreach ($formData['owners'] as $ownerData) {
                $ownerAddressString = 'N/A';
                if (isset($ownerData['address']) && is_array($ownerData['address'])) {
                    $addressParts = array_filter([
                        $ownerData['address']['streetAddress'] ?? '',
                        $ownerData['address']['city'] ?? '',
                        $ownerData['address']['state'] ?? '',
                        $ownerData['address']['zipCode'] ?? '',
                        $ownerData['address']['country'] ?? '',
                    ]);
                    $ownerAddressString = !empty($addressParts) ? implode(', ', $addressParts) : 'N/A';
                }
                
                $owners->push((object) [
                    'full_name' => $ownerData['fullName'] ?? '',
                    'ownership_percentage' => $ownerData['ownershipPercentage'] ?? 0,
                    'address' => $ownerAddressString,
                ]);
            }
        }

        // Format state information
        $stateName = $formData['state']['name'] ?? null;
        $stateFee = isset($formData['state']['cost']) ? number_format((float) $formData['state']['cost'], 2) : '0.00';

        // Check if UK plan
        $isUKPlan = stripos($pricingPlanName, 'uk') !== false || 
                   stripos($countryName, 'united kingdom') !== false ||
                   stripos($countryName, 'uk') !== false;

        return [
            'order' => null,
            'pricingPlanName' => $pricingPlanName,
            'countryName' => $countryName,
            'userEmail' => $userEmail,
            'paymentStatus' => 'Unpaid', // Always unpaid for new orders
            'company' => null,
            'companyName' => $formData['companyName'] ?? 'N/A',
            'companyAddressString' => $companyAddressString,
            'categoryLabels' => $categoryLabels,
            'stateName' => $stateName,
            'stateFee' => $stateFee,
            'owners' => $owners,
            'addressString' => $addressString,
            'isUKPlan' => $isUKPlan,
        ];
    }
}


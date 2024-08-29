<?php

namespace Achei\DisableCustomer\Plugin;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Controller\Account\LoginPost;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Customer\Model\Session;

class CheckCustomerLoginPermission
{
    protected const MODULE_ENABLED = 'disable_customer/general/enable';
    /** @var ScopeConfigInterface */
    protected scopeConfigInterface $scopeConfig;
    /** @var CustomerRepositoryInterface */
    protected CustomerRepositoryInterface $customerRepository;
    /** @var ManagerInterface **/
    protected ManagerInterface $messageManager;
    /** @var RedirectFactory */
    private RedirectFactory $resultRedirectFactory;
    /** @var Session */
    protected Session $session;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param CustomerRepositoryInterface $customerRepository
     * @param ManagerInterface $messageManager
     * @param Context $context
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        CustomerRepositoryInterface $customerRepository,
        ManagerInterface $messageManager,
        Context $context,
        Session $session,
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->customerRepository = $customerRepository;
        $this->messageManager = $messageManager;
        $this->resultRedirectFactory = $context->getResultRedirectFactory();
        $this->session = $session;
    }
    public function beforeExecute(LoginPost $subject)
    {
        $storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;
        $enable = $this->scopeConfig->getValue(self::MODULE_ENABLED, $storeScope);

        if ($enable && $subject->getRequest()->isPost()) {
            $loginData = $subject->getRequest()->getPost('login');
            $email = $loginData['username'];
            $customer = $this->customerRepository->get($email);
            if ($customer->getId()) {
                $customerData = $customer->__toArray();
                $isCustomerAllowedToLogin = $this->isAccountDisabled($customer, $customerData);
                if ($isCustomerAllowedToLogin) {
                    $this->messageManager->addErrorMessage(__('Your account is not approved.
                                Kindly contact website admin for assitance.'));
                    //$this->session->destroy();
                    // DOES NOT WORK?
                    return $this->resultRedirectFactory->create()
                        ->setPath('customer/account/login');
                    //@todo:: redirect to last visited url?
                }
            }
        }
    }
    /**
     * Check if customer is a vendor and account is disabled
     * @param $customer
     * @return bool
     */
    function isAccountDisabled($customer, $customerData)
    {
        $customAttribute = $customer->getCustomAttribute('customer_disabled');
        if (empty($customAttribute)) {
            return false;
        }
        return $customAttribute->getValue() == 1;
    }
}

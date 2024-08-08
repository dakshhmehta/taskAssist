# Transaction Module

This transaction module is designed to help build developer credit/debit system with support of
single or multiple ledger.


## Using the Module
To use the module, you need to use *__HasTransactions__* trait into your model, which will be one of
your ledger.

For example, in a banking system, a customer account is considered as ledger. So, it can be used as:

```php
    <?php

    use Modules\Transaction\Traits\HasTransactions;

    class BankAccount {
        use HasTransactions;
        .
        .
        .
    }
```

Once it has been used, a relation method, credit, debit method and transaction will automatically will be associated with the *__BankAccount__* model.


## Inserting Plan Transaction

Another way of inserting a transaction is using static method called add on Transaction model.

```php
    <?php

    Transaction::add(-100); // will insert debit entry of 100
    Transaction::add(100); // will insert credit entry of 100

    ?>
```

Once transaction is inserted, it will return the object of *__Modules\Transaction\Entities\Transaction__* model which has than two additional methods. 

1. associate
2. dissociate


### Associating Ledger to Transaction
```php
    <?php

    $transaction = Transaction::add(-100); // will insert debit entry of 100

    $transaction->associate(Account::first());

    ?>
```

OR

```php
    <?php

    $transaction = Transaction::add(-100); // will insert debit entry of 100

    $transaction->associate([
        Account::first(),
        Category::first(),
        Customer::first()
    ]);

    ?>
```

OR

```php
    <?php

    $transaction = Transaction::add(-100); // will insert debit entry of 100

    $transaction
        ->associate(Category::first())
        ->associate(Account::first());

    ?>
```

All of above method of associating ledger to transaction are same as using credit,debit method by using HasTransactions trait owned by any ledger as illustrated in very first example.


### Dissociating Ledger to Account
It is same as associating ledger to the model, just replace method call with *__dissociate__*.


## Support & Help

Contact Daksh on dakshhmehta@gmail.com for any help or feature request.
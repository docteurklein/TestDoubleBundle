# TestDoubleBundle

## What ?

A symfony bundle that eases creation of test doubles.

Using DIC tags, you can automatically replace a service with either a stub or a fake.

## Why ?

To improve isolation of tests and increase the precision and variation of test fixtures.

Usually, our behat suite is using real data, coming from database fixtures.  
This forces us to create gobal, universal, works-for-all fixtures.

A real database also implies to reset the state before each scenario.  
This process is slow, and is just a workaround for having broken isolation.

An ideal test suite would run each scenario using only in-memory repositories.  
Each scenario should define how the SUS behaves given a specific context.  
Having a global implicit context (the database fixtures) makes it really hard to test different cases.

One solution is to replace your repositories with stubs.  
Each scenario configures only the stubs required for it to work.  

> Note: Stubbed data is not resilient across processes,
> and thus doesn't fit for end-to-end testing like a typical mink+behat suite.

But now that repositories are doubled, how do you know if your real repositories still work?  
Well, that's the role of infrastructure tests. Only those run against a real backend,
be it a database for repositories, or a server for an http client.

To access the real services, just use `<original-id>.real`.

By doing that, you theoretically have a good coverage, isolation, speed  
and you can better catch the origin of a regression.

All this while applying [modelling by example](http://stakeholderwhisperer.com/posts/2014/10/introducing-modelling-by-example).

## How ?

### install

    composer require docteurklein/test-double-bundle --dev

### register the bundle

``` php

    public function registerBundles()
    {
        $bundles = [
            new \DocteurKlein\TestDoubleBundle,
            // …
        ];

        return $bundles;
    }
```

> Note: You might want to add this bundle only in test env.

### integrate with behat

This approach integrates very well with the [Symfony2Extension](https://github.com/Behat/Symfony2Extension/blob/master/doc/index.rst#injecting-services).

You can then inject the service and/or the prophecy in your context class.  
You can also inject the container and access all the services at once.


## Examples

> Note: The following examples use JmsDiExtraBundle to simplify code.

### Stubs

Stubs are created using [prophecy](https://github.com/phpspec/prophecy).

> Note: if you don't provide any tag attribute, then a stub is created.
> if no class or interface is given to the `stub` attribute, then a stub for the service class will be created.
> A stubbed class cannot be final.


 - First, define a stub DIC tag for the service

``` php
/**
 * @Service("github_client")
 * @Tag("test_double", attributes={"stub"="GithubClient"})
 */
final class GuzzleClient implements GithubClient
{
    public function addIssue(Issue $issue)
    {
        // …
    }
}
```

- Automatically, the original `github_client` service is replaced with the `github_client.stub` service.

In order to control this stub, you have to use the `github_client.prophecy` service:

``` php
$issue = new Issue('test');
$container->get('github_client.prophecy')->addIssue($issue)->willReturn(true);
```

### Fake

> Note: fakes are really just DIC aliases.

Imagine you have a service you want to double.

- First, create this service and add a tag with the corresponding fake service:

``` php
/**
 * @Service("github_client")
 * @Tag("test_double", attributes={"fake"="github_client.fake"})
 */
final class GuzzleClient implements GithubClient
{
    public function addIssue(Issue $issue)
    {
        // …
    }
}
```

 - Then, create a fake implementation and register it with the fake id:

``` php
/**
 * @Service("github_client.fake")
 */
final class FakeClient implements GithubClient
{
    public function addIssue(Issue $issue)
    {
        // …
    }
}
```

### Behat

> Note: We tagged `repo.invoices` and `http.client` as **stub**.

``` php
class Domain implements Context
{
    public function __construct($container)
    {
        $this->container = $container;
    }

    /**
     * @Given a building in "maintenance mode"
     */
    public function aBuildingInMaintenanceMode()
    {
        $this->building = new Building('BUILDING1337');
        $this->building->putInMaintenanceMode();
    }

    /**
     * @When its last unpaid invoice is being paid
     */
    public function itsLastUnpaidInvoiceIsBeingPaid()
    {
        $this->container
            ->get('repo.invoices.prophecy')
            ->findOneByReference('UNPAID04')
            ->willReturn(Invoice::ownedBy($this->building))
        ;
        $pay = $this->container->get('app.task.invoice.pay');
        $pay('UNPAID04');
    }

    /**
     * @Then it should be removed from maintenance mode
     */
    public function itShouldBeRemovedFromMaintenanceMode()
    {
        $this->container
            ->get('http.client.prophecy')
            ->removeFromMaintenanceMode('BUILDING1337')
            ->shouldHaveBeenCalled()
        ;

        $this->container->get('stub.prophet')->checkPredictions();
    }
}
```

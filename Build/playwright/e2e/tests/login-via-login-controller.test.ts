import { test, expect } from '@playwright/test';

test('Login via LoginController', async ({ page }) => {
  await page.goto('/');
  await page.getByRole('link', { name: 'Link to login page', exact: true }).click();
  await expect(page).toHaveURL(new RegExp('http://oidc.t3ext-oidc.test/'));

  await page.getByLabel('Username').fill('User1');
  await page.getByLabel('Password').fill('pwd');
  await page.getByRole('button', {name: 'Login'}).click()
  await expect(page).toHaveURL(new RegExp('/en/'));
  await expect(page.getByText('Username 1')).toBeVisible();

  await page.getByRole('button', {name: 'Logout'}).click();
  await expect(page.getByText('You have logged out.')).toBeVisible();

  await page.getByRole('link', { name: 'Link to login page', exact: true }).click();
  await expect(page.getByText('Username 1')).toBeVisible();

  await page.getByRole('button', {name: 'Logout'}).click();
  await expect(page.getByText('You have logged out.')).toBeVisible();
});

test('Login via LoginController with redirect url', async ({ page }) => {
  await page.goto('/');
  await page.getByRole('link', { name: 'Link to login page with redirect_url' }).click();
  await expect(page).toHaveURL(new RegExp('http://oidc.t3ext-oidc.test/'));

  await page.getByLabel('Username').fill('User1');
  await page.getByLabel('Password').fill('pwd');
  await page.getByRole('button', {name: 'Login'}).click()
  await expect(page).toHaveURL(new RegExp('/en/login-redirect-target/$'));

  await page.goto('/en/');
  await expect(page.getByText('Username 1')).toBeVisible();

  await page.getByRole('button', {name: 'Logout'}).click();
  await expect(page.getByText('You have logged out.')).toBeVisible();
});

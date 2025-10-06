import { test, expect } from '@playwright/test';

test('Login via login form', async ({ page }) => {
  await page.goto('https://v13.t3ext-oidc.test/');
  await page.getByRole('link', { name: 'Login with OpenID Connect' }).click();
  await expect(page).toHaveURL(new RegExp('http://oidc.t3ext-oidc.test/'));

  await page.getByLabel('Username').fill('User1');
  await page.getByLabel('Password').fill('pwd');
  await page.getByRole('button', {name: 'Login'}).click()
  await expect(page).toHaveURL(new RegExp('https://v13.t3ext-oidc.test/'));
  await expect(page.getByText('Username 1')).toBeVisible();

  await page.getByRole('button', {name: 'Logout'}).click();
  await expect(page.getByText('You have logged out.')).toBeVisible();

  await page.getByRole('link', { name: 'Login with OpenID Connect' }).click();
  await expect(page.getByText('Username 1')).toBeVisible();

  await page.getByRole('button', {name: 'Logout'}).click();
  await expect(page.getByText('You have logged out.')).toBeVisible();
});

import Database from '@ioc:Adonis/Lucid/Database'
import { test } from '@japa/runner'

test.group('One time links: New', () => {
  test('show a form to make a new link', async ({ client }) => {
    const response = await client.get('/1tl/new')
    response.assertStatus(200)

    response.assertTextIncludes('<form')
    response.assertTextIncludes('Your secret message')
    response.assertTextIncludes('Create')
  })
})

test.group('One time links: Create', (group) => {
  group.each.setup(async () => {
    await Database.beginGlobalTransaction()
    return () => Database.rollbackGlobalTransaction()
  })

  test('create a new one time link', async ({ client }) => {
    const response = await client
      .post('/1tl/new')
      .form({ message: 'my password is "tamarind chutney"' })
      .withCsrfToken()

    response.assertStatus(200)
    response.assertTextIncludes('successfully encrypted')
    response.assertTextIncludes('to share')
  })

  test('validation failure', async ({ client, route }) => {
    const response = await client
      .post(route('createOneTimeLink'))
      .form({
        message: ' ',
      })
      .withCsrfToken()
      .header('referrer', route('newOneTimeLink'))

    response.assertStatus(200)
    response.assertTextIncludes('Cannot be only whitespace.')
  })

  test('refuse to create a link if it is missing csrf token', async ({ client }) => {
    const response = await client
      .post('/1tl/new')
      .form({ message: 'my password is "tamarind chutney"' })

    response.assertStatus(403)
  })
})

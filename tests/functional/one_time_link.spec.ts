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

test.group('One time links: Create', () => {
  test('create a new one time link', async ({ client }) => {
    const response = await client
      .post('/1tl/create')
      .form({ message: 'my password is "tamarind chutney"' })
      .withCsrfToken()

    response.assertStatus(201)
    response.assertTextIncludes('successfully encrypted')
    response.assertTextIncludes('to share')
  })

  test('refuse to create a link if it is missing csrf token', async ({ client }) => {
    const response = await client
      .post('/1tl/create')
      .form({ message: 'my password is "tamarind chutney"' })

    response.assertStatus(403)
  })

  test('refuse to create a link if it is missing a message', async ({ client }) => {
    const response = await client.post('/1tl/create').form({ message: null }).withCsrfToken()

    response.assertStatus(400)
  })

  test('refuse to create a link if the message exceeds the max length', () => {})
})

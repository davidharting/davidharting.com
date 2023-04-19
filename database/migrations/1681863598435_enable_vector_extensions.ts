import BaseSchema from '@ioc:Adonis/Lucid/Schema'

export default class extends BaseSchema {
  public async up() {
    this.db.rawQuery('CREATE EXTENSION vector;')
  }

  public async down() {
    this.db.rawQuery('DROP EXTENSION vector;')
  }
}

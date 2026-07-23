#!/usr/bin/env ruby
# frozen_string_literal: true

require "json"
require "date"

DATA_FILE = "library_data.json"

class Book
  attr_accessor :id, :title, :author, :year, :borrowed, :borrower

  def initialize(id:, title:, author:, year:, borrowed: false, borrower: nil)
    @id = id
    @title = title
    @author = author
    @year = year
    @borrowed = borrowed
    @borrower = borrower
  end

  def borrow(name)
    return false if @borrowed

    @borrowed = true
    @borrower = name
    true
  end

  def return_book
    return false unless @borrowed

    @borrowed = false
    @borrower = nil
    true
  end

  def available?
    !@borrowed
  end

  def matches?(query)
    text = "#{@title} #{@author} #{@year}".downcase
    text.include?(query.downcase)
  end

  def to_h
    {
      id: @id,
      title: @title,
      author: @author,
      year: @year,
      borrowed: @borrowed,
      borrower: @borrower
    }
  end

  def self.from_h(data)
    new(
      id: data["id"],
      title: data["title"],
      author: data["author"],
      year: data["year"],
      borrowed: data["borrowed"],
      borrower: data["borrower"]
    )
  end
end

class Library
  attr_reader :books

  def initialize
    @books = []
    @next_id = 1
  end

  def load
    return unless File.exist?(DATA_FILE)

    data = JSON.parse(File.read(DATA_FILE))
    @books = data.fetch("books", []).map { |book| Book.from_h(book) }
    @next_id = data.fetch("next_id", calculate_next_id)
  rescue JSON::ParserError => error
    warn "Could not parse data file: #{error.message}"
  rescue StandardError => error
    warn "Could not load data: #{error.message}"
  end

  def save
    data = {
      next_id: @next_id,
      books: @books.map(&:to_h)
    }

    temporary_file = "#{DATA_FILE}.tmp"
    File.write(temporary_file, JSON.pretty_generate(data))
    File.rename(temporary_file, DATA_FILE)
  rescue StandardError => error
    warn "Could not save data: #{error.message}"
  end

  def add_book(title, author, year)
    book = Book.new(
      id: @next_id,
      title: title,
      author: author,
      year: year
    )

    @books << book
    @next_id += 1
    book
  end

  def remove_book(id)
    book = find_book(id)
    return false unless book

    @books.delete(book)
    true
  end

  def find_book(id)
    @books.find { |book| book.id == id }
  end

  def search(query)
    @books.select { |book| book.matches?(query) }
  end

  def available_books
    @books.select(&:available?)
  end

  def borrowed_books
    @books.reject(&:available?)
  end

  def sorted_books
    @books.sort_by { |book| [book.title.downcase, book.author.downcase] }
  end

  def calculate_next_id
    maximum = @books.map(&:id).max || 0
    maximum + 1
  end

  def statistics
    {
      total: @books.length,
      available: available_books.length,
      borrowed: borrowed_books.length
    }
  end
end

class LibraryApp
  def initialize
    @library = Library.new
    @library.load
  end

  def run
    print_banner

    loop do
      print_menu
      choice = prompt("Choose an option")

      case choice
      when "1"
        add_book
      when "2"
        list_books
      when "3"
        search_books
      when "4"
        borrow_book
      when "5"
        return_book
      when "6"
        remove_book
      when "7"
        show_statistics
      when "8"
        save_and_exit
        break
      else
        puts "Invalid option. Enter a number from 1 to 8."
      end
    end
  end

  private

  def print_banner
    puts
    puts "=" * 50
    puts "             SIMPLE RUBY LIBRARY"
    puts "=" * 50
    puts "Data file: #{DATA_FILE}"
    puts
  end

  def print_menu
    puts
    puts "1. Add a book"
    puts "2. List all books"
    puts "3. Search books"
    puts "4. Borrow a book"
    puts "5. Return a book"
    puts "6. Remove a book"
    puts "7. Show statistics"
    puts "8. Save and exit"
    puts
  end

  def prompt(message)
    print "#{message}: "
    input = gets
    return "" if input.nil?

    input.strip
  end

  def required_prompt(message)
    loop do
      value = prompt(message)
      return value unless value.empty?

      puts "This value cannot be empty."
    end
  end

  def read_integer(message)
    value = prompt(message)
    Integer(value)
  rescue ArgumentError
    puts "Please enter a valid integer."
    nil
  end

  def add_book
    title = required_prompt("Book title")
    author = required_prompt("Author")
    year = read_integer("Publication year")
    return unless year

    current_year = Date.today.year

    if year < 0 || year > current_year + 1
      puts "Publication year is outside the accepted range."
      return
    end

    book = @library.add_book(title, author, year)
    @library.save
    puts "Added book ##{book.id}: #{book.title}"
  end

  def list_books
    books = @library.sorted_books

    if books.empty?
      puts "The library is empty."
      return
    end

    puts
    puts "-" * 70

    books.each do |book|
      print_book(book)
    end

    puts "-" * 70
    puts "#{books.length} book(s) found."
  end

  def search_books
    query = required_prompt("Search title, author, or year")
    results = @library.search(query)

    if results.empty?
      puts "No books matched #{query.inspect}."
      return
    end

    results.each { |book| print_book(book) }
    puts "#{results.length} matching book(s)."
  end

  def borrow_book
    id = read_integer("Book ID")
    return unless id

    book = @library.find_book(id)

    unless book
      puts "Book ##{id} was not found."
      return
    end

    unless book.available?
      puts "That book is already borrowed by #{book.borrower}."
      return
    end

    borrower = required_prompt("Borrower name")
    book.borrow(borrower)
    @library.save
    puts "#{book.title} was borrowed by #{borrower}."
  end

  def return_book
    id = read_integer("Book ID")
    return unless id

    book = @library.find_book(id)

    unless book
      puts "Book ##{id} was not found."
      return
    end

    unless book.return_book
      puts "That book is not currently borrowed."
      return
    end

    @library.save
    puts "#{book.title} was returned."
  end

  def remove_book
    id = read_integer("Book ID")
    return unless id

    book = @library.find_book(id)

    unless book
      puts "Book ##{id} was not found."
      return
    end

    confirmation = prompt("Delete #{book.title.inspect}? Enter yes")

    unless confirmation.downcase == "yes"
      puts "Deletion cancelled."
      return
    end

    @library.remove_book(id)
    @library.save
    puts "Book ##{id} was removed."
  end

  def show_statistics
    stats = @library.statistics

    puts
    puts "Library statistics"
    puts "------------------"
    puts "Total books:     #{stats[:total]}"
    puts "Available books: #{stats[:available]}"
    puts "Borrowed books:  #{stats[:borrowed]}"
  end

  def print_book(book)
    status =
      if book.available?
        "Available"
      else
        "Borrowed by #{book.borrower}"
      end

    puts "##{book.id} | #{book.title}"
    puts "Author: #{book.author}"
    puts "Year: #{book.year}"
    puts "Status: #{status}"
    puts
  end

  def save_and_exit
    @library.save
    puts "Library data saved."
    puts "Goodbye!"
  end
end

if __FILE__ == $PROGRAM_NAME
  LibraryApp.new.run
end

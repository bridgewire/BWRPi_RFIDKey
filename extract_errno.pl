#!/usr/bin/perl -w

# Author: Christiana Johnson
# Copywrite 2014
#
# The purpose of this is to look-up and map locally accurate errno values to
# their ordinary names as given in 'man errno'. It requires the ability to run
# "man errno" and to run the gcc preprocessor. Also requires File::Temp and the
# ability to use it to create a temporary file. The output is only given on
# stdout.  runs without arguments.

use strict;
use warnings;
use File::Temp;

my $nb="";
my $ofh = File::Temp->new( UNLINK => 1, SUFFIX => '.c' ); # unlink during destroy().

open( IN, "man -t errno |" ) || die "couldn't get errno information from the man page.\n";

while(<IN>) { chomp; $nb .= $_; }  # read the data in from the man page. strip newlines.

if( length($nb) )
{
  print $ofh "#include <errno.h>\n\nint main() {\n";  # begin C file.

  # run: $ man -t errno | perl -wpe 'chomp'
  # to take a look at what this program parses.
  # XXX  this is undoubtedly quite brittle, unfortunately.  if you know a
  # better way please tell me.
  while( length($nb) > 0 )
  {
    if( $nb =~ s/F1\((E[\sA-Z]*)\)108(.+?)F0(.*)$/$3/ )
    {
      my $f1 = $1;
      my $lcl = $2;

      my @g = ($lcl =~ m/\(([\sA-Z]+)\)[-.\d\s]*/g);
      if( $#g > -1 ) { $f1 .= join("",@g); }
      $f1 =~ s/\s//g;

      # 'zing' is a silly marker to ease later parsing.
      print $ofh "    printf(\"zing: $f1\", $f1);\n" ;
    }
    else
    {
      $nb = "";
    }
  }

  print $ofh "    return 0;\n}\n";    # end C file.
  $ofh->flush();                      # File::Temp is also an IO::HANDLE, so can flush.

  # run the gcc pre-processor and capture output.  the pre-processor
  # reliably does a correct constant definition lookup.
  open( PREOUT, 'gcc -E '.$ofh->filename.' |' ) || die "running C pre-processor failed.";
  my $inmain = 0;
  while( my $l = <PREOUT> )
  {
    if($l=~/^ int main\(/){ $inmain = 1; }

    if( $l =~ /^\s+printf\("zing: ([A-Z]+)", (\d+)\)/ )
    {
      print "define('$1', $2);\n";   # 'const $1 = $2;' in a class might be better that define.
    }
    elsif( $inmain )
    {
      if( $l =~ /^\s+return 0;$/ ) { $inmain = 0; }
      else                         { print "error. no match: $l\n"; }
    } 
  }

  close( PREOUT );
}


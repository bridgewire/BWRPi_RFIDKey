#!/usr/bin/perl -w

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

  while( length($nb) > 0 )
  {
    if( $nb =~ s/F1\((E[\sA-Z]*)\)108(.+?)F0(.*)$/$3/ )
    {
      my $f1 = $1;
      my $lcl = $2;

      my @g = ($lcl =~ m/\(([\sA-Z]+)\)[-.\d\s]*/g);
      if( $#g > -1 ) { $f1 .= join("",@g); }
      $f1 =~ s/\s//g;

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

